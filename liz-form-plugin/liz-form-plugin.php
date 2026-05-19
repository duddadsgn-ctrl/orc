<?php
/**
 * Plugin Name: Liz Form — (R)Evolução da Palavra
 * Description: Armazena respostas do formulário de inscrição diretamente no banco WordPress. Visualize e exporte pelo painel admin.
 * Version:     1.0.0
 * Author:      Liz Maria
 * Text Domain: liz-form
 */

defined( 'ABSPATH' ) || exit;

// ── Ativação: cria a tabela ───────────────────────────────────────────────────
register_activation_hook( __FILE__, 'lizform_create_table' );

function lizform_create_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'liz_submissions';
    $collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id           mediumint(9)  NOT NULL AUTO_INCREMENT,
        data_envio   datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        nome         varchar(255)  NOT NULL DEFAULT '',
        instagram    varchar(255)  NOT NULL DEFAULT '',
        email        varchar(255)  NOT NULL DEFAULT '',
        cidade       varchar(255)  NOT NULL DEFAULT '',
        idade        varchar(10)   NOT NULL DEFAULT '',
        profissao    varchar(255)  NOT NULL DEFAULT '',
        pergunta_1   text,
        pergunta_2   text,
        pergunta_3   text,
        pergunta_4   text,
        pergunta_5   text,
        PRIMARY KEY  (id)
    ) {$collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// ── REST API ──────────────────────────────────────────────────────────────────
add_action( 'rest_api_init', 'lizform_register_routes' );

function lizform_register_routes() {
    register_rest_route( 'liz-form/v1', '/submit', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'lizform_handle_submit',
        'permission_callback' => '__return_true',
    ] );
}

function lizform_handle_submit( WP_REST_Request $req ) {
    global $wpdb;
    $table = $wpdb->prefix . 'liz_submissions';
    $raw   = $req->get_json_params();

    if ( empty( $raw['nome'] ) || empty( $raw['email'] ) ) {
        return new WP_Error( 'invalid_data', 'Nome e e-mail são obrigatórios.', [ 'status' => 400 ] );
    }

    $inserted = $wpdb->insert( $table, [
        'nome'       => sanitize_text_field( $raw['nome']       ?? '' ),
        'instagram'  => sanitize_text_field( $raw['instagram']  ?? '' ),
        'email'      => sanitize_email(      $raw['email']      ?? '' ),
        'cidade'     => sanitize_text_field( $raw['cidade']     ?? '' ),
        'idade'      => sanitize_text_field( $raw['idade']      ?? '' ),
        'profissao'  => sanitize_text_field( $raw['profissao']  ?? '' ),
        'pergunta_1' => sanitize_textarea_field( $raw['pergunta_1'] ?? '' ),
        'pergunta_2' => sanitize_textarea_field( $raw['pergunta_2'] ?? '' ),
        'pergunta_3' => sanitize_textarea_field( $raw['pergunta_3'] ?? '' ),
        'pergunta_4' => sanitize_textarea_field( $raw['pergunta_4'] ?? '' ),
        'pergunta_5' => sanitize_textarea_field( $raw['pergunta_5'] ?? '' ),
    ] );

    if ( $inserted === false ) {
        return new WP_Error( 'db_error', 'Erro ao salvar no banco.', [ 'status' => 500 ] );
    }

    return rest_ensure_response( [ 'success' => true, 'id' => $wpdb->insert_id ] );
}

// ── Admin ─────────────────────────────────────────────────────────────────────
add_action( 'admin_menu', 'lizform_admin_menu' );

function lizform_admin_menu() {
    add_menu_page(
        '(R)Evolução da Palavra — Inscrições',
        'Liz Form',
        'manage_options',
        'liz-form-submissions',
        'lizform_admin_page',
        'dashicons-feedback',
        25
    );
}

function lizform_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'liz_submissions';

    if ( isset( $_GET['export'] ) && current_user_can( 'manage_options' ) ) {
        lizform_export_csv( $table );
        return;
    }

    $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY data_envio DESC" );
    $total = count( $rows );

    $perguntas = [
        1 => 'O que mais te incomoda na forma como você vive hoje?',
        2 => 'O que continua se repetindo mesmo depois de muitos esforços?',
        3 => 'Qual livro, frase ou experiência mais mudou sua forma de se ver?',
        4 => 'O que te fez se interessar pelo (R)Evolução da Palavra?',
        5 => 'Se nada mudar, o que você teme que aconteça com sua vida?',
    ];

    ?>
    <style>
        .liz-card { background:#fff; border:1px solid #e2e2e2; border-radius:8px; padding:24px; margin-bottom:20px; }
        .liz-card-header { display:flex; align-items:baseline; gap:12px; border-bottom:2px solid #FFE06D; padding-bottom:10px; margin-bottom:16px; }
        .liz-card-name { font-size:16px; font-weight:700; color:#111; }
        .liz-card-date { font-size:12px; color:#888; }
        .liz-info-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:10px; margin-bottom:16px; }
        .liz-info-item label { display:block; font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.5px; }
        .liz-info-item span { font-size:14px; color:#222; }
        .liz-question { background:#fafafa; border-left:3px solid #FFE06D; padding:10px 14px; margin-bottom:10px; border-radius:0 4px 4px 0; }
        .liz-question .q-label { font-size:11px; color:#888; margin-bottom:4px; text-transform:uppercase; letter-spacing:.5px; }
        .liz-question .q-text { font-size:13px; color:#222; white-space:pre-wrap; }
    </style>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:12px;">
            Inscrições — (R)Evolução da Palavra
            <span style="font-size:13px;background:#FFE06D;color:#000;padding:3px 12px;border-radius:20px;font-weight:700;"><?= $total ?> respostas</span>
        </h1>
        <p>
            <a class="button button-primary" href="<?= esc_url( add_query_arg( 'export', '1' ) ) ?>">⬇ Exportar CSV</a>
        </p>

        <?php if ( ! $rows ) : ?>
            <p>Nenhuma inscrição ainda.</p>
        <?php else : ?>
            <?php foreach ( $rows as $row ) : ?>
            <div class="liz-card">
                <div class="liz-card-header">
                    <span class="liz-card-name"><?= esc_html( $row->nome ) ?></span>
                    <span class="liz-card-date"><?= esc_html( $row->data_envio ) ?></span>
                </div>
                <div class="liz-info-grid">
                    <div class="liz-info-item"><label>Instagram</label><span><?= esc_html( $row->instagram ) ?></span></div>
                    <div class="liz-info-item"><label>E-mail</label><span><?= esc_html( $row->email ) ?></span></div>
                    <div class="liz-info-item"><label>Cidade/Estado</label><span><?= esc_html( $row->cidade ) ?></span></div>
                    <div class="liz-info-item"><label>Idade</label><span><?= esc_html( $row->idade ) ?></span></div>
                    <div class="liz-info-item"><label>Profissão</label><span><?= esc_html( $row->profissao ) ?></span></div>
                </div>
                <?php for ( $i = 1; $i <= 5; $i++ ) : $col = 'pergunta_' . $i; ?>
                <div class="liz-question">
                    <div class="q-label">Pergunta <?= $i ?> — <?= esc_html( $perguntas[ $i ] ) ?></div>
                    <div class="q-text"><?= esc_html( $row->$col ) ?></div>
                </div>
                <?php endfor; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}

function lizform_export_csv( $table ) {
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY data_envio DESC", ARRAY_A );

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="inscricoes-' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Pragma: no-cache' );

    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // UTF-8 BOM para Excel

    fputcsv( $out, [
        'ID', 'Data', 'Nome', 'Instagram', 'E-mail', 'Cidade', 'Idade', 'Profissão',
        'Pergunta 1', 'Pergunta 2', 'Pergunta 3', 'Pergunta 4', 'Pergunta 5',
    ], ';' );

    foreach ( $rows as $row ) {
        fputcsv( $out, [
            $row['id'],       $row['data_envio'], $row['nome'],       $row['instagram'],
            $row['email'],    $row['cidade'],      $row['idade'],      $row['profissao'],
            $row['pergunta_1'], $row['pergunta_2'], $row['pergunta_3'],
            $row['pergunta_4'], $row['pergunta_5'],
        ], ';' );
    }

    fclose( $out );
    exit;
}
