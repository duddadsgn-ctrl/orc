<?php
/**
 * Plugin Name: Liz Form — Formulário Progressivo
 * Description: Popup estilo Typeform injetado automaticamente. Adicione a classe CSS configurada em qualquer botão do site para abrir o formulário. Todas as respostas ficam salvas no WordPress.
 * Version:     2.0.0
 * Author:      Liz Maria
 * Text Domain: liz-form
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════════════
// 1. DEFAULTS & HELPERS
// ═══════════════════════════════════════════════════════════════

function lizform_defaults() {
    return [
        /* Visual */
        'bg_color'        => '#0A0A0A',
        'accent_color'    => '#FFE06D',
        'btn_text_color'  => '#0A0A0A',
        /* Trigger */
        'trigger_class'   => 'liz-form-trigger',
        /* Redirect */
        'redirect_url'    => '',
        'redirect_delay'  => '3',
        /* Tela inicial */
        'welcome_tag'     => 'Liz Maria · (R)Evolução da Palavra',
        'welcome_title'   => 'Uma jornada começa com <em>uma escolha.</em>',
        'welcome_p1'      => 'Antes de prosseguirmos, quero te conhecer de verdade.',
        'welcome_p2'      => 'Responda com honestidade — isso é o que torna tudo possível.',
        'welcome_btn'     => 'Começar',
        /* Perguntas */
        'q1'  => 'O que mais te <em>incomoda</em> na forma como você vive hoje?',
        'q2'  => 'O que você percebe que continua <em>se repetindo</em> na sua vida, mesmo depois de muitos esforços para mudar?',
        'q3'  => 'Qual <em>livro, frase ou experiência</em> mais mudou sua forma de enxergar a si mesma?',
        'q4'  => 'O que te fez se interessar pelo <em>(R)Evolução da Palavra?</em>',
        'q5'  => 'Se nada mudar nos próximos anos, o que você <em>teme</em> que aconteça com a sua vida?',
        /* Sucesso */
        'success_title' => 'Obrigada,',
        'success_sub'   => 'Suas respostas chegaram com muito cuidado.<br>Você será redirecionada para finalizar sua inscrição...',
    ];
}

function lizform_get() {
    return wp_parse_args( get_option( 'liz_form_settings', [] ), lizform_defaults() );
}

function lizform_table() {
    global $wpdb;
    return $wpdb->prefix . 'liz_submissions';
}

$allowed_html = [ 'em' => [], 'strong' => [], 'br' => [] ];

// ═══════════════════════════════════════════════════════════════
// 2. ACTIVAÇÃO — cria tabela
// ═══════════════════════════════════════════════════════════════

register_activation_hook( __FILE__, 'lizform_activate' );

function lizform_activate() {
    global $wpdb;
    $t   = lizform_table();
    $sql = "CREATE TABLE IF NOT EXISTS {$t} (
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
    ) {$wpdb->get_charset_collate()};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// ═══════════════════════════════════════════════════════════════
// 3. ADMIN — menus e páginas
// ═══════════════════════════════════════════════════════════════

add_action( 'admin_menu', 'lizform_admin_menu' );

function lizform_admin_menu() {
    add_menu_page( 'Liz Form', 'Liz Form', 'manage_options', 'liz-form', 'lizform_page_submissions', 'dashicons-feedback', 25 );
    add_submenu_page( 'liz-form', 'Inscrições',     'Inscrições',     'manage_options', 'liz-form',          'lizform_page_submissions' );
    add_submenu_page( 'liz-form', 'Configurações',  'Configurações',  'manage_options', 'liz-form-settings', 'lizform_page_settings' );
}

// Enqueue color picker only on our settings page
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'liz-form_page_liz-form-settings' ) return;
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){ $(".liz-color").wpColorPicker(); });' );
} );

// ─── Página: Inscrições ─────────────────────────────────────────
function lizform_page_submissions() {
    global $wpdb;
    $t = lizform_table();

    if ( isset( $_GET['export'] ) && current_user_can( 'manage_options' ) ) {
        lizform_export_csv(); return;
    }

    if ( isset( $_GET['delete'] ) && current_user_can( 'manage_options' ) ) {
        check_admin_referer( 'liz_delete_' . intval( $_GET['delete'] ) );
        $wpdb->delete( $t, [ 'id' => intval( $_GET['delete'] ) ] );
        echo '<div class="notice notice-success"><p>Inscrição removida.</p></div>';
    }

    $rows  = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY data_envio DESC" );
    $total = count( $rows );

    $perguntas = [
        1 => 'O que mais te incomoda?',
        2 => 'O que continua se repetindo?',
        3 => 'Livro, frase ou experiência?',
        4 => 'Por que o (R)Evolução da Palavra?',
        5 => 'O que você teme que aconteça?',
    ];
    ?>
    <style>
        .liz-card{background:#fff;border:1px solid #e2e2e2;border-radius:8px;padding:22px 24px;margin-bottom:18px}
        .liz-card-head{display:flex;align-items:baseline;justify-content:space-between;border-bottom:2px solid #FFE06D;padding-bottom:10px;margin-bottom:14px}
        .liz-card-name{font-size:16px;font-weight:700;color:#111}
        .liz-card-date{font-size:12px;color:#999}
        .liz-info{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;margin-bottom:14px}
        .liz-info-item label{display:block;font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
        .liz-info-item span{font-size:13px;color:#222}
        .liz-q{background:#fafafa;border-left:3px solid #FFE06D;padding:8px 12px;margin-bottom:8px;border-radius:0 4px 4px 0}
        .liz-q-label{font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
        .liz-q-text{font-size:13px;color:#333;white-space:pre-wrap}
        .liz-delete{font-size:11px;color:#d00;text-decoration:none}
        .liz-delete:hover{color:#900}
        .liz-badge{background:#FFE06D;color:#000;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;margin-left:10px}
    </style>
    <div class="wrap">
        <h1>Inscrições — (R)Evolução da Palavra <span class="liz-badge"><?= $total ?> respostas</span></h1>
        <p><a class="button button-primary" href="<?= esc_url( add_query_arg( 'export', '1' ) ) ?>">⬇ Exportar CSV</a></p>

        <?php if ( ! $rows ) : ?>
            <p style="color:#666;">Nenhuma inscrição ainda. O formulário está ativo — assim que alguém preencher, aparece aqui.</p>
        <?php else : ?>
            <?php foreach ( $rows as $row ) : ?>
                <div class="liz-card">
                    <div class="liz-card-head">
                        <div>
                            <span class="liz-card-name"><?= esc_html( $row->nome ) ?></span>
                            <span class="liz-card-date"> &nbsp;·&nbsp; <?= esc_html( $row->data_envio ) ?></span>
                        </div>
                        <a class="liz-delete"
                           href="<?= esc_url( wp_nonce_url( add_query_arg( 'delete', $row->id ), 'liz_delete_' . $row->id ) ) ?>"
                           onclick="return confirm('Remover esta inscrição?')">✕ Remover</a>
                    </div>
                    <div class="liz-info">
                        <div class="liz-info-item"><label>Instagram</label><span><?= esc_html( $row->instagram ) ?></span></div>
                        <div class="liz-info-item"><label>E-mail</label><span><?= esc_html( $row->email ) ?></span></div>
                        <div class="liz-info-item"><label>Cidade / Estado</label><span><?= esc_html( $row->cidade ) ?></span></div>
                        <div class="liz-info-item"><label>Idade</label><span><?= esc_html( $row->idade ) ?></span></div>
                        <div class="liz-info-item"><label>Profissão</label><span><?= esc_html( $row->profissao ) ?></span></div>
                    </div>
                    <?php for ( $i = 1; $i <= 5; $i++ ) : $col = 'pergunta_' . $i; ?>
                        <div class="liz-q">
                            <div class="liz-q-label"><?= $i ?> — <?= esc_html( $perguntas[$i] ) ?></div>
                            <div class="liz-q-text"><?= esc_html( $row->$col ) ?></div>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}

// ─── Página: Configurações ──────────────────────────────────────
function lizform_page_settings() {
    $s    = lizform_get();
    $saved = isset( $_GET['saved'] );
    ?>
    <style>
        .liz-settings-wrap{max-width:760px}
        .liz-section{background:#fff;border:1px solid #ddd;border-radius:8px;padding:24px 28px;margin-bottom:24px}
        .liz-section h2{margin-top:0;padding-bottom:10px;border-bottom:2px solid #FFE06D;font-size:15px;color:#222}
        .liz-row{display:grid;grid-template-columns:200px 1fr;align-items:start;gap:12px;margin-bottom:16px}
        .liz-row label{padding-top:6px;font-size:13px;color:#444;font-weight:600}
        .liz-row input[type=text],.liz-row input[type=url],.liz-row input[type=number],.liz-row textarea{width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px}
        .liz-row textarea{height:64px;resize:vertical;font-family:inherit}
        .liz-hint-text{font-size:11px;color:#999;margin-top:4px}
        .liz-preview-box{background:var(--liz-bg,#0A0A0A);border-radius:6px;padding:18px 22px;margin-top:12px}
        .liz-preview-box p{color:rgba(255,255,255,.55);font-size:13px;margin:0}
        .liz-preview-box strong{color:#fff;font-size:18px;font-family:Georgia,serif;display:block;margin-bottom:6px}
        .liz-color-row{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
        .liz-color-item{display:flex;flex-direction:column;gap:4px}
        .liz-color-item label{font-size:12px;color:#555;font-weight:600}
    </style>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p>✓ Configurações salvas com sucesso.</p></div>
    <?php endif; ?>

    <div class="wrap liz-settings-wrap">
        <h1>Configurações — Liz Form</h1>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="lizform_save_settings">
            <?php wp_nonce_field( 'lizform_settings' ); ?>

            <!-- ── Visual ── -->
            <div class="liz-section">
                <h2>🎨 Identidade Visual</h2>
                <div class="liz-color-row">
                    <div class="liz-color-item">
                        <label>Cor de Fundo</label>
                        <input type="text" name="bg_color" value="<?= esc_attr( $s['bg_color'] ) ?>" class="liz-color">
                    </div>
                    <div class="liz-color-item">
                        <label>Cor de Acento (botões, destaques)</label>
                        <input type="text" name="accent_color" value="<?= esc_attr( $s['accent_color'] ) ?>" class="liz-color">
                    </div>
                    <div class="liz-color-item">
                        <label>Texto dos Botões</label>
                        <input type="text" name="btn_text_color" value="<?= esc_attr( $s['btn_text_color'] ) ?>" class="liz-color">
                    </div>
                </div>
            </div>

            <!-- ── Comportamento ── -->
            <div class="liz-section">
                <h2>⚙️ Comportamento</h2>
                <div class="liz-row">
                    <label>Classe CSS do Trigger</label>
                    <div>
                        <input type="text" name="trigger_class" value="<?= esc_attr( $s['trigger_class'] ) ?>">
                        <p class="liz-hint-text">Adicione esta classe em qualquer botão do site para abrir o formulário. Padrão: <code>liz-form-trigger</code></p>
                    </div>
                </div>
                <div class="liz-row">
                    <label>URL de Redirecionamento</label>
                    <div>
                        <input type="url" name="redirect_url" value="<?= esc_attr( $s['redirect_url'] ) ?>" placeholder="https://...">
                        <p class="liz-hint-text">Página para onde a pessoa vai após enviar o formulário.</p>
                    </div>
                </div>
                <div class="liz-row">
                    <label>Tempo de espera (segundos)</label>
                    <input type="number" name="redirect_delay" value="<?= esc_attr( $s['redirect_delay'] ) ?>" min="1" max="30" style="max-width:80px">
                </div>
            </div>

            <!-- ── Tela Inicial ── -->
            <div class="liz-section">
                <h2>🏠 Tela Inicial (Boas-vindas)</h2>
                <div class="liz-row">
                    <label>Tag / Subtag</label>
                    <input type="text" name="welcome_tag" value="<?= esc_attr( $s['welcome_tag'] ) ?>">
                </div>
                <div class="liz-row">
                    <label>Título Principal</label>
                    <div>
                        <textarea name="welcome_title"><?= esc_textarea( $s['welcome_title'] ) ?></textarea>
                        <p class="liz-hint-text">Use <code>&lt;em&gt;texto&lt;/em&gt;</code> para deixar palavras em dourado/itálico.</p>
                    </div>
                </div>
                <div class="liz-row">
                    <label>Parágrafo 1</label>
                    <input type="text" name="welcome_p1" value="<?= esc_attr( $s['welcome_p1'] ) ?>">
                </div>
                <div class="liz-row">
                    <label>Parágrafo 2</label>
                    <input type="text" name="welcome_p2" value="<?= esc_attr( $s['welcome_p2'] ) ?>">
                </div>
                <div class="liz-row">
                    <label>Texto do Botão</label>
                    <input type="text" name="welcome_btn" value="<?= esc_attr( $s['welcome_btn'] ) ?>" style="max-width:240px">
                </div>
            </div>

            <!-- ── Perguntas ── -->
            <div class="liz-section">
                <h2>❓ Perguntas</h2>
                <?php
                $labels = [
                    'q1' => 'Pergunta 1 — Incomodo',
                    'q2' => 'Pergunta 2 — Repetição',
                    'q3' => 'Pergunta 3 — Transformação',
                    'q4' => 'Pergunta 4 — Interesse',
                    'q5' => 'Pergunta 5 — Temor',
                ];
                foreach ( $labels as $key => $label ) : ?>
                    <div class="liz-row">
                        <label><?= $label ?></label>
                        <div>
                            <textarea name="<?= $key ?>"><?= esc_textarea( $s[$key] ) ?></textarea>
                            <p class="liz-hint-text">Use <code>&lt;em&gt;texto&lt;/em&gt;</code> para destaque em dourado.</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Tela de Sucesso ── -->
            <div class="liz-section">
                <h2>✅ Tela de Sucesso</h2>
                <div class="liz-row">
                    <label>Título (antes do nome)</label>
                    <input type="text" name="success_title" value="<?= esc_attr( $s['success_title'] ) ?>" style="max-width:220px">
                </div>
                <div class="liz-row">
                    <label>Mensagem</label>
                    <textarea name="success_sub"><?= esc_textarea( $s['success_sub'] ) ?></textarea>
                </div>
            </div>

            <p><button type="submit" class="button button-primary button-large">💾 Salvar Configurações</button></p>
        </form>
    </div>
    <?php
}

// ─── Salvar settings ────────────────────────────────────────────
add_action( 'admin_post_lizform_save_settings', 'lizform_save_settings' );

function lizform_save_settings() {
    check_admin_referer( 'lizform_settings' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sem permissão.' );

    global $allowed_html;
    $defaults = lizform_defaults();
    $rich     = [ 'welcome_title', 'welcome_p1', 'welcome_p2', 'q1', 'q2', 'q3', 'q4', 'q5', 'success_sub' ];

    $new = [];
    foreach ( $defaults as $k => $default ) {
        $raw    = $_POST[ $k ] ?? $default;
        $new[$k] = in_array( $k, $rich )
            ? wp_kses( $raw, [ 'em' => [], 'strong' => [], 'br' => [] ] )
            : sanitize_text_field( $raw );
    }

    update_option( 'liz_form_settings', $new );
    wp_redirect( admin_url( 'admin.php?page=liz-form-settings&saved=1' ) );
    exit;
}

// ═══════════════════════════════════════════════════════════════
// 4. REST API
// ═══════════════════════════════════════════════════════════════

add_action( 'rest_api_init', function () {
    register_rest_route( 'liz-form/v1', '/submit', [
        'methods'             => 'POST',
        'callback'            => 'lizform_api_submit',
        'permission_callback' => '__return_true',
    ] );
} );

function lizform_api_submit( WP_REST_Request $req ) {
    global $wpdb;
    $raw = $req->get_json_params();

    if ( empty( $raw['nome'] ) || empty( $raw['email'] ) ) {
        return new WP_Error( 'invalid', 'Nome e e-mail são obrigatórios.', [ 'status' => 400 ] );
    }

    $ok = $wpdb->insert( lizform_table(), [
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

    if ( false === $ok ) {
        return new WP_Error( 'db', 'Erro ao salvar no banco.', [ 'status' => 500 ] );
    }

    return rest_ensure_response( [ 'success' => true, 'id' => $wpdb->insert_id ] );
}

// ═══════════════════════════════════════════════════════════════
// 5. EXPORT CSV
// ═══════════════════════════════════════════════════════════════

function lizform_export_csv() {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sem permissão.' );

    $rows = $wpdb->get_results( 'SELECT * FROM ' . lizform_table() . ' ORDER BY data_envio DESC', ARRAY_A );

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="inscricoes-' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Pragma: no-cache' );

    $out = fopen( 'php://output', 'w' );
    fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // UTF-8 BOM para Excel

    fputcsv( $out, [ 'ID', 'Data', 'Nome', 'Instagram', 'E-mail', 'Cidade', 'Idade', 'Profissão',
        'Pergunta 1', 'Pergunta 2', 'Pergunta 3', 'Pergunta 4', 'Pergunta 5' ], ';' );

    foreach ( $rows as $r ) {
        fputcsv( $out, [ $r['id'], $r['data_envio'], $r['nome'], $r['instagram'], $r['email'],
            $r['cidade'], $r['idade'], $r['profissao'], $r['pergunta_1'], $r['pergunta_2'],
            $r['pergunta_3'], $r['pergunta_4'], $r['pergunta_5'] ], ';' );
    }

    fclose( $out );
    exit;
}

// ═══════════════════════════════════════════════════════════════
// 6. FRONTEND — injeta popup no rodapé de todas as páginas
// ═══════════════════════════════════════════════════════════════

add_action( 'wp_footer', 'lizform_render_popup' );

function lizform_render_popup() {
    $s = lizform_get();

    $bg      = esc_attr( $s['bg_color'] );
    $accent  = esc_attr( $s['accent_color'] );
    $btn_txt = esc_attr( $s['btn_text_color'] );
    $cls     = esc_js( $s['trigger_class'] );

    $redirect_url   = esc_js( $s['redirect_url'] );
    $redirect_delay = intval( $s['redirect_delay'] ) * 1000;

    $api_url = esc_js( rest_url( 'liz-form/v1/submit' ) );

    ?>
    <!-- ── Liz Form Popup ──────────────────────────────────────── -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">

    <style id="liz-form-style">
    #liz-ov,#liz-ov *{box-sizing:border-box;margin:0;padding:0}
    #liz-ov{
      position:fixed;inset:0;z-index:999999;
      background:rgba(0,0,0,.88);
      display:flex;align-items:center;justify-content:center;padding:24px;
      opacity:0;visibility:hidden;pointer-events:none;
      transition:opacity .35s ease,visibility .35s ease;
    }
    #liz-ov.liz-open{opacity:1;visibility:visible;pointer-events:all}

    #liz-modal{
      position:relative;width:100%;max-width:720px;height:660px;
      background:<?= $bg ?>;overflow:hidden;
      border:1px solid rgba(255,255,255,.07);
      flex-shrink:0;
    }

    /* X */
    #liz-x{
      position:absolute;top:14px;right:18px;z-index:50;
      background:transparent;border:none;
      color:rgba(255,255,255,.3);font-size:22px;line-height:1;
      cursor:pointer;padding:4px 6px;
      transition:color .2s;font-family:sans-serif;
    }
    #liz-x:hover{color:<?= $accent ?>}

    /* Barra de progresso */
    #liz-bar{
      position:absolute;top:0;left:0;height:2px;
      background:<?= $accent ?>;width:0%;
      transition:width .6s ease;z-index:40;
    }

    /* Ornamentos */
    .lf-orn{position:absolute;pointer-events:none;opacity:.04}

    /* Steps */
    .lf-s{
      position:absolute;inset:0;
      display:flex;align-items:center;justify-content:center;
      padding:56px 60px 44px;overflow-y:auto;
      opacity:0;transform:translateY(36px);pointer-events:none;
      transition:opacity .5s ease,transform .5s ease;
    }
    .lf-s.active{opacity:1;transform:translateY(0);pointer-events:all}
    .lf-s.exit{opacity:0;transform:translateY(-36px);pointer-events:none}

    .lf-box{width:100%}

    .lf-label{
      font-family:'Open Sans',sans-serif;font-size:11px;font-weight:600;
      letter-spacing:3px;text-transform:uppercase;
      color:<?= $accent ?>;opacity:.7;margin-bottom:12px;
    }

    .lf-box h2{
      font-family:'Playfair Display',serif;
      font-size:clamp(22px,2.8vw,36px);font-weight:600;line-height:1.25;
      color:#fff;margin-bottom:10px;
    }
    .lf-box h2 em{font-style:italic;color:<?= $accent ?>}

    .lf-sub{
      font-family:'Open Sans',sans-serif;font-size:14px;
      color:rgba(255,255,255,.5);margin-bottom:28px;line-height:1.65;
    }

    .lf-input,.lf-ta{
      display:block;width:100%;background:transparent;border:none;
      border-bottom:1.5px solid <?= $accent ?>55;
      color:#fff;font-family:'Open Sans',sans-serif;
      font-size:19px;padding:10px 0;outline:none;
      caret-color:<?= $accent ?>;transition:border-color .3s;
    }
    .lf-input:focus,.lf-ta:focus{border-bottom-color:<?= $accent ?>}
    .lf-input::placeholder,.lf-ta::placeholder{color:rgba(255,255,255,.2);font-size:16px}
    .lf-ta{resize:none;min-height:90px;font-size:16px;line-height:1.7}

    .lf-err{
      font-family:'Open Sans',sans-serif;font-size:12px;color:#ff7070;
      margin-top:6px;height:16px;opacity:0;transition:opacity .2s;
    }
    .lf-err.show{opacity:1}

    .lf-acts{display:flex;align-items:center;gap:16px;margin-top:20px}

    .lf-ok{
      display:inline-flex;align-items:center;gap:8px;
      background:<?= $accent ?>;color:<?= $btn_txt ?>;border:none;
      padding:12px 26px;font-family:'Open Sans',sans-serif;
      font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;
      cursor:pointer;transition:background .25s,transform .2s;
    }
    .lf-ok:hover{background:#fff;transform:translateY(-2px)}

    .lf-bk{
      background:transparent;border:none;color:rgba(255,255,255,.3);
      font-family:'Open Sans',sans-serif;font-size:11px;
      letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;
      transition:color .25s;padding:0;
    }
    .lf-bk:hover{color:<?= $accent ?>}

    .lf-hint{margin-top:12px;font-size:11px;color:rgba(255,255,255,.2);font-family:'Open Sans',sans-serif}
    .lf-hint kbd{
      display:inline-block;background:rgba(255,255,255,.07);
      border:1px solid rgba(255,255,255,.12);border-radius:3px;
      padding:1px 5px;font-size:10px;color:rgba(255,255,255,.28);
    }

    /* Welcome */
    .lf-tag{
      font-family:'Open Sans',sans-serif;font-size:10px;font-weight:600;
      letter-spacing:4px;text-transform:uppercase;
      color:<?= $accent ?>;opacity:.6;margin-bottom:18px;
    }
    #lf0 h1{
      font-family:'Playfair Display',serif;
      font-size:clamp(28px,3.4vw,48px);font-weight:700;line-height:1.15;
      color:#fff;margin-bottom:14px;
    }
    #lf0 h1 em{color:<?= $accent ?>;font-style:italic}
    .lf-div{width:36px;height:1.5px;background:<?= $accent ?>;opacity:.45;margin:14px 0}
    #lf0 p{font-family:'Open Sans',sans-serif;font-size:14px;line-height:1.75;color:rgba(255,255,255,.52)}

    .lf-start{
      display:inline-flex;align-items:center;gap:10px;
      background:<?= $accent ?>;color:<?= $btn_txt ?>;border:none;
      padding:14px 36px;margin-top:18px;
      font-family:'Open Sans',sans-serif;font-size:11px;font-weight:700;
      letter-spacing:2.5px;text-transform:uppercase;cursor:pointer;
      transition:background .25s,transform .2s,box-shadow .25s;
    }
    .lf-start:hover{background:#fff;transform:translateY(-3px);box-shadow:0 12px 36px <?= $accent ?>30}

    /* Sucesso */
    .lf-ico{
      width:52px;height:52px;border:1.5px solid <?= $accent ?>;border-radius:50%;
      display:flex;align-items:center;justify-content:center;
      font-size:20px;color:<?= $accent ?>;margin-bottom:20px;
    }
    .lf-dots{display:flex;gap:8px;margin-top:24px}
    .lf-dot{width:7px;height:7px;background:<?= $accent ?>;border-radius:50%;animation:lfp 1.5s infinite}
    .lf-dot:nth-child(2){animation-delay:.25s}
    .lf-dot:nth-child(3){animation-delay:.5s}
    @keyframes lfp{0%,80%,100%{opacity:.22;transform:scale(.7)}40%{opacity:1;transform:scale(1)}}

    @media(max-width:767px){
      #liz-ov{padding:0;align-items:flex-end}
      #liz-modal{max-width:100%;height:580px;border:none;border-top:1px solid rgba(255,255,255,.07);border-radius:12px 12px 0 0}
      .lf-s{padding:52px 24px 28px}
      .lf-box h2{font-size:clamp(19px,5vw,26px);margin-bottom:8px}
      #lf0 h1{font-size:clamp(24px,7vw,34px);margin-bottom:10px}
      #lf0 p{font-size:13px}
      .lf-div,.lf-tag{margin-bottom:10px}
      .lf-ta{min-height:75px}
      .lf-start{padding:13px 28px;margin-top:14px}
      .lf-acts{margin-top:14px}
      .lf-hint{display:none}
      .lf-sub{font-size:13px;margin-bottom:18px}
    }
    </style>

    <div id="liz-ov">
      <div id="liz-modal">
        <button id="liz-x" aria-label="Fechar">✕</button>
        <div id="liz-bar"></div>

        <svg class="lf-orn" style="top:-130px;right:-130px;width:460px;height:460px;" viewBox="0 0 460 460" fill="none">
          <circle cx="230" cy="230" r="195" stroke="<?= $accent ?>" stroke-width="1"/>
          <circle cx="230" cy="230" r="134" stroke="<?= $accent ?>" stroke-width=".6"/>
          <circle cx="230" cy="230" r="72"  stroke="<?= $accent ?>" stroke-width=".4"/>
        </svg>
        <svg class="lf-orn" style="bottom:-60px;left:-60px;width:220px;height:220px;" viewBox="0 0 220 220" fill="none">
          <circle cx="110" cy="110" r="90" stroke="<?= $accent ?>" stroke-width=".6"/>
        </svg>

        <!-- Step 0: Boas-vindas -->
        <div class="lf-s active" id="lf0">
          <div class="lf-box">
            <div class="lf-tag"><?= esc_html( $s['welcome_tag'] ) ?></div>
            <h1><?= wp_kses( $s['welcome_title'], [ 'em' => [], 'br' => [] ] ) ?></h1>
            <div class="lf-div"></div>
            <p><?= esc_html( $s['welcome_p1'] ) ?></p>
            <p style="margin-top:6px"><?= esc_html( $s['welcome_p2'] ) ?></p>
            <button class="lf-start" onclick="lfNext()">
              <?= esc_html( $s['welcome_btn'] ) ?>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>

        <!-- Step 1: Nome -->
        <div class="lf-s" id="lf1">
          <div class="lf-box">
            <div class="lf-label">01 —</div>
            <h2>Qual é o seu <em>nome completo?</em></h2>
            <input class="lf-input" type="text" id="lf-nome" placeholder="Escreva seu nome aqui..." autocomplete="name">
            <div class="lf-err" id="lerr-1">Por favor, informe seu nome completo.</div>
            <div class="lf-acts">
              <button class="lf-ok" onclick="lfNext()">OK <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></button>
              <button class="lf-bk" onclick="lfBack()">← Voltar</button>
            </div>
            <div class="lf-hint"><kbd>Enter</kbd> para continuar</div>
          </div>
        </div>

        <!-- Step 2: Instagram -->
        <div class="lf-s" id="lf2">
          <div class="lf-box">
            <div class="lf-label">02 —</div>
            <h2>Qual é o seu <em>Instagram?</em></h2>
            <p class="lf-sub">Pode colocar o @ ou apenas o usuário.</p>
            <input class="lf-input" type="text" id="lf-insta" placeholder="@seuperfil">
            <div class="lf-err" id="lerr-2">Por favor, informe seu Instagram.</div>
            <div class="lf-acts">
              <button class="lf-ok" onclick="lfNext()">OK <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></button>
              <button class="lf-bk" onclick="lfBack()">← Voltar</button>
            </div>
            <div class="lf-hint"><kbd>Enter</kbd> para continuar</div>
          </div>
        </div>

        <!-- Step 3: Email -->
        <div class="lf-s" id="lf3">
          <div class="lf-box">
            <div class="lf-label">03 —</div>
            <h2>Qual é o seu <em>e-mail?</em></h2>
            <input class="lf-input" type="email" id="lf-email" placeholder="seuemail@exemplo.com" autocomplete="email">
            <div class="lf-err" id="lerr-3">Por favor, informe um e-mail válido.</div>
            <div class="lf-acts">
              <button class="lf-ok" onclick="lfNext()">OK <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></button>
              <button class="lf-bk" onclick="lfBack()">← Voltar</button>
            </div>
            <div class="lf-hint"><kbd>Enter</kbd> para continuar</div>
          </div>
        </div>

        <!-- Step 4: Cidade -->
        <div class="lf-s" id="lf4">
          <div class="lf-box">
            <div class="lf-label">04 —</div>
            <h2>De onde você é? <em>Cidade e Estado.</em></h2>
            <input class="lf-input" type="text" id="lf-cidade" placeholder="Ex: São Paulo, SP">
            <div class="lf-err" id="lerr-4">Por favor, informe sua cidade e estado.</div>
            <div class="lf-acts">
              <button class="lf-ok" onclick="lfNext()">OK <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></button>
              <button class="lf-bk" onclick="lfBack()">← Voltar</button>
            </div>
            <div class="lf-hint"><kbd>Enter</kbd> para continuar</div>
          </div>
        </div>

        <!-- Step 5: Idade -->
        <div class="lf-s" id="lf5">
          <div class="lf-box">
            <div class="lf-label">05 —</div>
            <h2>Qual é a sua <em>idade?</em></h2>
            <input class="lf-input" type="number" id="lf-idade" placeholder="Sua idade" min="1" max="120">
            <div class="lf-err" id="lerr-5">Por favor, informe sua idade.</div>
            <div class="lf-acts">
              <button class="lf-ok" onclick="lfNext()">OK <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></button>
              <button class="lf-bk" onclick="lfBack()">← Voltar</button>
            </div>
            <div class="lf-hint"><kbd>Enter</kbd> para continuar</div>
          </div>
        </div>

        <!-- Step 6: Profissão -->
        <div class="lf-s" id="lf6">
          <div class="lf-box">
            <div class="lf-label">06 —</div>
            <h2>Qual é a sua <em>profissão</em> ou área de atuação?</h2>
            <input class="lf-input" type="text" id="lf-prof" placeholder="Ex: Empreendedora, Terapeuta...">
            <div class="lf-err" id="lerr-6">Por favor, informe sua profissão.</div>
            <div class="lf-acts">
              <button class="lf-ok" onclick="lfNext()">OK <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg></button>
              <button class="lf-bk" onclick="lfBack()">← Voltar</button>
            </div>
            <div class="lf-hint"><kbd>Enter</kbd> para continuar</div>
          </div>
        </div>

        <!-- Steps 7–11: Perguntas -->
        <?php
        $qs = [
            7  => [ 'num' => '01', 'text' => $s['q1'], 'id' => 'lf-q1' ],
            8  => [ 'num' => '02', 'text' => $s['q2'], 'id' => 'lf-q2' ],
            9  => [ 'num' => '03', 'text' => $s['q3'], 'id' => 'lf-q3' ],
            10 => [ 'num' => '04', 'text' => $s['q4'], 'id' => 'lf-q4' ],
            11 => [ 'num' => '05', 'text' => $s['q5'], 'id' => 'lf-q5' ],
        ];
        foreach ( $qs as $n => $q ) :
            $is_last = ( $n === 11 );
        ?>
        <div class="lf-s" id="lf<?= $n ?>">
          <div class="lf-box">
            <div class="lf-label">Pergunta <?= $q['num'] ?> —</div>
            <h2><?= wp_kses( $q['text'], [ 'em' => [], 'strong' => [] ] ) ?></h2>
            <textarea class="lf-ta" id="<?= $q['id'] ?>" placeholder="Escreva com liberdade..."></textarea>
            <div class="lf-err" id="lerr-<?= $n ?>">Por favor, responda a pergunta.</div>
            <div class="lf-acts">
              <button class="lf-ok" onclick="lfNext()">
                <?= $is_last ? 'Enviar' : 'OK' ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <?php if ( $is_last ) : ?><path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
                  <?php else : ?><path d="M20 6L9 17l-5-5"/><?php endif; ?>
                </svg>
              </button>
              <button class="lf-bk" onclick="lfBack()">← Voltar</button>
            </div>
            <div class="lf-hint"><kbd>Shift</kbd>&nbsp;+&nbsp;<kbd>Enter</kbd> nova linha &nbsp;·&nbsp; <kbd>Enter</kbd> <?= $is_last ? 'enviar' : 'continuar' ?></div>
          </div>
        </div>
        <?php endforeach; ?>

        <!-- Step 12: Sucesso -->
        <div class="lf-s" id="lf12">
          <div class="lf-box" style="display:flex;flex-direction:column;align-items:center;text-align:center">
            <div class="lf-ico">✦</div>
            <h2><?= esc_html( $s['success_title'] ) ?> <em id="lf-nome-ok">querida</em>.</h2>
            <p style="font-family:'Open Sans',sans-serif;font-size:14px;color:rgba(255,255,255,.5);margin-top:12px;line-height:1.7">
              <?= wp_kses( $s['success_sub'], [ 'br' => [], 'em' => [] ] ) ?>
            </p>
            <div class="lf-dots"><div class="lf-dot"></div><div class="lf-dot"></div><div class="lf-dot"></div></div>
          </div>
        </div>

      </div><!-- /#liz-modal -->
    </div><!-- /#liz-ov -->

    <script id="liz-form-js">
    (function(){
      'use strict';
      var cur=0, TOTAL=12;
      var api='<?= $api_url ?>';
      var redirectUrl='<?= $redirect_url ?>';
      var redirectDelay=<?= $redirect_delay ?>;
      var triggerClass='<?= $cls ?>';

      var fields=[
        null,
        {el:'lf-nome',  err:'lerr-1', type:'text'},
        {el:'lf-insta', err:'lerr-2', type:'text'},
        {el:'lf-email', err:'lerr-3', type:'email'},
        {el:'lf-cidade',err:'lerr-4', type:'text'},
        {el:'lf-idade', err:'lerr-5', type:'text'},
        {el:'lf-prof',  err:'lerr-6', type:'text'},
        {el:'lf-q1',    err:'lerr-7', type:'text'},
        {el:'lf-q2',    err:'lerr-8', type:'text'},
        {el:'lf-q3',    err:'lerr-9', type:'text'},
        {el:'lf-q4',    err:'lerr-10',type:'text'},
        {el:'lf-q5',    err:'lerr-11',type:'text'},
      ];

      function g(id){return document.getElementById(id);}

      function validate(n){
        var f=fields[n]; if(!f) return true;
        var el=g(f.el), er=g(f.err);
        var v=(el.value||'').trim();
        if(!v){er.classList.add('show');el.focus();return false;}
        if(f.type==='email'&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)){er.classList.add('show');el.focus();return false;}
        er.classList.remove('show');
        return true;
      }

      function clearErr(n){var f=fields[n];if(f)g(f.err).classList.remove('show');}

      function setBar(n){g('liz-bar').style.width=(n===0?0:Math.round(n/TOTAL*100))+'%';}

      function goTo(next){
        var c=g('lf'+cur), nx=g('lf'+next);
        c.classList.add('exit');c.classList.remove('active');
        setTimeout(function(){c.classList.remove('exit');},560);
        nx.classList.add('active');
        cur=next;setBar(cur);
        setTimeout(function(){var f=fields[cur];if(f){var el=g(f.el);if(el)el.focus();}},320);
      }

      function reset(){
        document.querySelectorAll('.lf-s').forEach(function(s){s.classList.remove('active','exit');});
        g('lf0').classList.add('active');
        cur=0;setBar(0);
        fields.forEach(function(f){
          if(!f)return;
          var el=g(f.el);if(el)el.value='';
          var er=g(f.err);if(er)er.classList.remove('show');
        });
      }

      function submit(){
        goTo(TOTAL);
        var nome=g('lf-nome').value.trim();
        g('lf-nome-ok').textContent=nome.split(' ')[0]||'querida';

        var data={
          nome:nome,
          instagram:g('lf-insta').value.trim(),
          email:g('lf-email').value.trim(),
          cidade:g('lf-cidade').value.trim(),
          idade:g('lf-idade').value.trim(),
          profissao:g('lf-prof').value.trim(),
          pergunta_1:g('lf-q1').value.trim(),
          pergunta_2:g('lf-q2').value.trim(),
          pergunta_3:g('lf-q3').value.trim(),
          pergunta_4:g('lf-q4').value.trim(),
          pergunta_5:g('lf-q5').value.trim(),
        };

        fetch(api,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
          .catch(function(e){console.warn('[LizForm]',e);});

        if(redirectUrl){
          setTimeout(function(){window.location.href=redirectUrl;},redirectDelay);
        }
      }

      window.lfNext=function(){
        if(cur===0){goTo(1);return;}
        if(!validate(cur))return;
        if(cur===TOTAL-1){submit();return;}
        goTo(cur+1);
      };

      window.lfBack=function(){
        if(cur<=1){goTo(0);return;}
        clearErr(cur);
        var c=g('lf'+cur),p=g('lf'+(cur-1));
        c.classList.remove('active');
        p.style.cssText+='opacity:0;transform:translateY(-36px);';
        p.classList.add('active');
        requestAnimationFrame(function(){requestAnimationFrame(function(){p.style.opacity='';p.style.transform='';});});
        cur--;setBar(cur);
      };

      /* Teclado */
      document.addEventListener('keydown',function(e){
        if(!g('liz-ov').classList.contains('liz-open'))return;
        if(e.key==='Escape'){closeModal();return;}
        if(e.key==='Enter'&&e.target.tagName==='INPUT'){e.preventDefault();window.lfNext();return;}
        if(e.key==='Enter'&&!e.shiftKey&&e.target.tagName==='TEXTAREA'){e.preventDefault();window.lfNext();return;}
        var f=fields[cur];if(f&&g(f.err))g(f.err).classList.remove('show');
      });

      /* Modal */
      function openModal(){g('liz-ov').classList.add('liz-open');document.body.style.overflow='hidden';}
      function closeModal(){g('liz-ov').classList.remove('liz-open');document.body.style.overflow='';setTimeout(reset,400);}

      g('liz-x').addEventListener('click',closeModal);
      g('liz-ov').addEventListener('click',function(e){if(e.target===this)closeModal();});

      /* Trigger — escuta cliques no documento inteiro, funciona com qualquer elemento que tenha a classe configurada */
      document.addEventListener('click',function(e){
        if(e.target.closest('.'+triggerClass)){e.preventDefault();openModal();}
      });

      setBar(0);
    }());
    </script>
    <!-- ── /Liz Form Popup ─────────────────────────────────────── -->
    <?php
}
