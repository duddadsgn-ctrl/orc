<?php
/**
 * Plugin Name: Liz Form — Formulário Progressivo
 * Description: Popup estilo Typeform. Instale, ative, adicione a classe CSS configurada em qualquer botão — pronto. Todas as respostas salvas no WordPress.
 * Version:     3.0.0
 * Author:      Liz Maria
 * Text Domain: liz-form
 */

defined( 'ABSPATH' ) || exit;

// ═══════════════════════════════════════════════════════
// DEFAULTS
// ═══════════════════════════════════════════════════════
function lizform_defaults() {
    return [
        /* Visual */
        'bg_color'              => '#0A0A0A',
        'accent_color'          => '#FFE06D',
        'btn_text_color'        => '#0A0A0A',
        /* Trigger */
        'trigger_class'         => 'liz-form-trigger',
        /* Redirect */
        'redirect_url'          => '',
        'redirect_delay'        => '3',
        /* Tela inicial */
        'welcome_tag'           => 'Liz Maria &nbsp;·&nbsp; (R)Evolução da Palavra',
        'welcome_title'         => 'Uma jornada começa<br>com <em>uma escolha.</em>',
        'welcome_p1'            => 'Antes de prosseguirmos, quero te conhecer de verdade.',
        'welcome_p2'            => 'Responda com honestidade — isso é o que torna tudo possível.',
        'welcome_btn'           => 'Começar',
        /* Campos de identificação — enabled + label */
        'f_nome_on'             => '1',
        'f_nome_q'              => 'Qual é o seu <em>nome completo?</em>',
        'f_insta_on'            => '1',
        'f_insta_q'             => 'Qual é o seu <em>Instagram?</em>',
        'f_insta_sub'           => 'Pode colocar o @ ou apenas o usuário.',
        'f_email_on'            => '1',
        'f_email_q'             => 'Qual é o seu <em>e-mail?</em>',
        'f_cidade_on'           => '1',
        'f_cidade_q'            => 'De onde você é? <em>Cidade e Estado.</em>',
        'f_idade_on'            => '1',
        'f_idade_q'             => 'Qual é a sua <em>idade?</em>',
        'f_prof_on'             => '1',
        'f_prof_q'              => 'Qual é a sua <em>profissão</em> ou área de atuação?',
        /* Perguntas — enabled + texto */
        'q1_on'                 => '1',
        'q1_text'               => 'O que mais te <em>incomoda</em> na forma como você vive hoje?',
        'q2_on'                 => '1',
        'q2_text'               => 'O que você percebe que continua <em>se repetindo</em> na sua vida, mesmo depois de muitos esforços para mudar?',
        'q3_on'                 => '1',
        'q3_text'               => 'Qual <em>livro, frase ou experiência</em> mais mudou sua forma de enxergar a si mesma?',
        'q4_on'                 => '1',
        'q4_text'               => 'O que te fez se interessar pelo <em>(R)Evolução da Palavra?</em>',
        'q5_on'                 => '1',
        'q5_text'               => 'Se nada mudar nos próximos anos, o que você <em>teme</em> que aconteça com a sua vida?',
        /* Sucesso */
        'success_sub'           => 'Suas respostas chegaram com muito cuidado.<br>Você será redirecionada para finalizar sua inscrição...',
    ];
}

function lizform_get() {
    return wp_parse_args( get_option( 'liz_form_settings', [] ), lizform_defaults() );
}

// ═══════════════════════════════════════════════════════
// ATIVAÇÃO — cria tabela
// ═══════════════════════════════════════════════════════
register_activation_hook( __FILE__, 'lizform_activate' );
function lizform_activate() {
    global $wpdb;
    $t   = $wpdb->prefix . 'liz_submissions';
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

// ═══════════════════════════════════════════════════════
// ADMIN — menus
// ═══════════════════════════════════════════════════════
add_action( 'admin_menu', 'lizform_admin_menu' );
function lizform_admin_menu() {
    add_menu_page( 'Liz Form', 'Liz Form', 'manage_options', 'liz-form', 'lizform_page_submissions', 'dashicons-feedback', 25 );
    add_submenu_page( 'liz-form', 'Inscrições',    'Inscrições',    'manage_options', 'liz-form',          'lizform_page_submissions' );
    add_submenu_page( 'liz-form', 'Configurações', 'Configurações', 'manage_options', 'liz-form-settings', 'lizform_page_settings' );
}

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, 'liz-form' ) === false ) return;
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){ $(".liz-color").wpColorPicker(); });' );
} );

// ═══════════════════════════════════════════════════════
// PÁGINA: INSCRIÇÕES
// ═══════════════════════════════════════════════════════
function lizform_page_submissions() {
    global $wpdb;
    $t = $wpdb->prefix . 'liz_submissions';

    if ( isset( $_GET['export'] ) && current_user_can( 'manage_options' ) ) { lizform_export_csv(); return; }

    if ( isset( $_GET['delete'] ) && current_user_can( 'manage_options' ) ) {
        check_admin_referer( 'liz_del_' . intval( $_GET['delete'] ) );
        $wpdb->delete( $t, [ 'id' => intval( $_GET['delete'] ) ] );
        echo '<div class="notice notice-success"><p>Inscrição removida.</p></div>';
    }

    $rows  = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY data_envio DESC" );
    $total = count( $rows );
    $s     = lizform_get();

    // Mapa de labels das perguntas ativas
    $q_labels = [];
    for ( $i = 1; $i <= 5; $i++ ) {
        if ( ! empty( $s[ "q{$i}_on" ] ) ) {
            $q_labels[ $i ] = wp_strip_all_tags( $s[ "q{$i}_text" ] );
        }
    }
    ?>
    <style>
        .liz-card{background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:20px 24px;margin-bottom:16px}
        .liz-ch{display:flex;align-items:baseline;justify-content:space-between;border-bottom:2px solid #FFE06D;padding-bottom:8px;margin-bottom:12px}
        .liz-cn{font-size:16px;font-weight:700;color:#111}.liz-cd{font-size:12px;color:#999}
        .liz-ig{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:8px;margin-bottom:12px}
        .liz-ii label{display:block;font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px}
        .liz-ii span{font-size:13px;color:#222}
        .liz-qq{background:#fafafa;border-left:3px solid #FFE06D;padding:8px 12px;margin-bottom:8px;border-radius:0 4px 4px 0}
        .liz-ql{font-size:10px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
        .liz-qr{font-size:13px;color:#333;white-space:pre-wrap}
        .liz-del{font-size:11px;color:#c00;text-decoration:none}.liz-del:hover{color:#800}
        .liz-badge{background:#FFE06D;color:#000;padding:2px 12px;border-radius:20px;font-size:12px;font-weight:700;margin-left:10px}
    </style>
    <div class="wrap">
        <h1>Inscrições — (R)Evolução da Palavra <span class="liz-badge"><?= $total ?> respostas</span></h1>
        <p><a class="button button-primary" href="<?= esc_url( add_query_arg( 'export', '1' ) ) ?>">⬇ Exportar CSV</a></p>
        <?php if ( ! $rows ) : ?>
            <p style="color:#666;margin-top:12px;">Nenhuma inscrição ainda. Assim que alguém preencher o formulário, aparecerá aqui.</p>
        <?php endif; ?>
        <?php foreach ( $rows as $row ) : ?>
        <div class="liz-card">
            <div class="liz-ch">
                <div><span class="liz-cn"><?= esc_html( $row->nome ) ?></span>
                     <span class="liz-cd"> · <?= esc_html( $row->data_envio ) ?></span></div>
                <a class="liz-del" href="<?= esc_url( wp_nonce_url( add_query_arg( 'delete', $row->id ), 'liz_del_' . $row->id ) ) ?>"
                   onclick="return confirm('Remover esta inscrição?')">✕ Remover</a>
            </div>
            <div class="liz-ig">
                <?php if ( ! empty( $s['f_insta_on'] ) ) : ?><div class="liz-ii"><label>Instagram</label><span><?= esc_html( $row->instagram ) ?></span></div><?php endif; ?>
                <?php if ( ! empty( $s['f_email_on'] ) ) : ?><div class="liz-ii"><label>E-mail</label><span><?= esc_html( $row->email ) ?></span></div><?php endif; ?>
                <?php if ( ! empty( $s['f_cidade_on'] ) ) : ?><div class="liz-ii"><label>Cidade/Estado</label><span><?= esc_html( $row->cidade ) ?></span></div><?php endif; ?>
                <?php if ( ! empty( $s['f_idade_on'] ) ) : ?><div class="liz-ii"><label>Idade</label><span><?= esc_html( $row->idade ) ?></span></div><?php endif; ?>
                <?php if ( ! empty( $s['f_prof_on'] ) ) : ?><div class="liz-ii"><label>Profissão</label><span><?= esc_html( $row->profissao ) ?></span></div><?php endif; ?>
            </div>
            <?php foreach ( $q_labels as $qi => $ql ) :
                $col = 'pergunta_' . $qi; ?>
            <div class="liz-qq">
                <div class="liz-ql"><?= $qi ?>. <?= esc_html( $ql ) ?></div>
                <div class="liz-qr"><?= esc_html( $row->$col ) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// ═══════════════════════════════════════════════════════
// PÁGINA: CONFIGURAÇÕES
// ═══════════════════════════════════════════════════════
function lizform_page_settings() {
    $s     = lizform_get();
    $saved = isset( $_GET['saved'] );

    $id_fields = [
        [ 'key' => 'nome',   'label' => 'Nome Completo',        'q_key' => 'f_nome_q',   'sub_key' => '' ],
        [ 'key' => 'insta',  'label' => 'Instagram',            'q_key' => 'f_insta_q',  'sub_key' => 'f_insta_sub' ],
        [ 'key' => 'email',  'label' => 'E-mail',               'q_key' => 'f_email_q',  'sub_key' => '' ],
        [ 'key' => 'cidade', 'label' => 'Cidade / Estado',      'q_key' => 'f_cidade_q', 'sub_key' => '' ],
        [ 'key' => 'idade',  'label' => 'Idade',                'q_key' => 'f_idade_q',  'sub_key' => '' ],
        [ 'key' => 'prof',   'label' => 'Profissão / Atuação',  'q_key' => 'f_prof_q',   'sub_key' => '' ],
    ];
    ?>
    <style>
        .liz-wrap{max-width:780px}
        .liz-sec{background:#fff;border:1px solid #ddd;border-radius:8px;padding:22px 26px;margin-bottom:22px}
        .liz-sec h2{margin:0 0 14px;padding-bottom:10px;border-bottom:2px solid #FFE06D;font-size:14px;color:#222}
        .liz-row{display:grid;grid-template-columns:190px 1fr;align-items:start;gap:10px;margin-bottom:14px}
        .liz-row>label{padding-top:7px;font-size:13px;color:#444;font-weight:600}
        .liz-row input[type=text],.liz-row input[type=url],.liz-row input[type=number],.liz-row textarea{
            width:100%;padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:13px;}
        .liz-row textarea{height:56px;resize:vertical;font-family:inherit}
        .liz-note{font-size:11px;color:#999;margin-top:3px}
        code{background:#f0f0f0;padding:1px 5px;border-radius:3px;font-size:12px}

        /* Field rows with toggle */
        .liz-field-row{border:1px solid #eee;border-radius:6px;padding:14px 16px;margin-bottom:10px;background:#fafafa}
        .liz-field-head{display:flex;align-items:center;gap:10px;margin-bottom:10px}
        .liz-field-head strong{font-size:13px;color:#333}
        .liz-toggle{position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0}
        .liz-toggle input{opacity:0;width:0;height:0}
        .liz-slider{position:absolute;inset:0;background:#ccc;border-radius:22px;cursor:pointer;transition:.3s}
        .liz-slider:before{content:"";position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
        .liz-toggle input:checked+.liz-slider{background:#FFE06D}
        .liz-toggle input:checked+.liz-slider:before{transform:translateX(18px)}
        .liz-field-body{display:grid;grid-template-columns:1fr;gap:8px}
        .liz-field-body label{font-size:12px;color:#666;font-weight:600}
        .liz-field-body input,.liz-field-body textarea{width:100%;padding:6px 9px;border:1px solid #ccc;border-radius:4px;font-size:13px}
        .liz-field-body textarea{height:52px;resize:vertical;font-family:inherit}
        .liz-colors{display:flex;gap:20px;flex-wrap:wrap}
        .liz-c-item{display:flex;flex-direction:column;gap:4px}
        .liz-c-item label{font-size:12px;font-weight:600;color:#555}
    </style>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible"><p>✓ Configurações salvas.</p></div>
    <?php endif; ?>

    <div class="wrap liz-wrap">
        <h1>Configurações — Liz Form</h1>
        <form method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ) ?>">
            <input type="hidden" name="action" value="lizform_save">
            <?php wp_nonce_field( 'lizform_save' ); ?>

            <!-- Visual -->
            <div class="liz-sec">
                <h2>🎨 Identidade Visual</h2>
                <div class="liz-colors">
                    <div class="liz-c-item"><label>Cor de Fundo</label>
                        <input type="text" name="bg_color" value="<?= esc_attr($s['bg_color']) ?>" class="liz-color"></div>
                    <div class="liz-c-item"><label>Cor de Acento (botões / destaques)</label>
                        <input type="text" name="accent_color" value="<?= esc_attr($s['accent_color']) ?>" class="liz-color"></div>
                    <div class="liz-c-item"><label>Texto dos Botões</label>
                        <input type="text" name="btn_text_color" value="<?= esc_attr($s['btn_text_color']) ?>" class="liz-color"></div>
                </div>
            </div>

            <!-- Comportamento -->
            <div class="liz-sec">
                <h2>⚙️ Comportamento</h2>
                <div class="liz-row">
                    <label>Classe CSS do Trigger</label>
                    <div>
                        <input type="text" name="trigger_class" value="<?= esc_attr($s['trigger_class']) ?>">
                        <p class="liz-note">Adicione esta classe em qualquer botão do Elementor para abrir o popup. Em: <strong>Botão → Avançado → Classe CSS</strong></p>
                    </div>
                </div>
                <div class="liz-row">
                    <label>URL de Redirecionamento</label>
                    <div>
                        <input type="url" name="redirect_url" value="<?= esc_attr($s['redirect_url']) ?>" placeholder="https://...">
                        <p class="liz-note">Página para onde vai após enviar o formulário.</p>
                    </div>
                </div>
                <div class="liz-row">
                    <label>Aguardar antes de redirecionar</label>
                    <div style="display:flex;align-items:center;gap:8px">
                        <input type="number" name="redirect_delay" value="<?= esc_attr($s['redirect_delay']) ?>" min="1" max="30" style="max-width:70px">
                        <span style="font-size:13px;color:#666">segundos</span>
                    </div>
                </div>
            </div>

            <!-- Tela inicial -->
            <div class="liz-sec">
                <h2>🏠 Tela Inicial (Boas-vindas)</h2>
                <div class="liz-row"><label>Tag / Eyebrow</label>
                    <input type="text" name="welcome_tag" value="<?= esc_attr($s['welcome_tag']) ?>"></div>
                <div class="liz-row"><label>Título Principal</label>
                    <div>
                        <textarea name="welcome_title"><?= esc_textarea($s['welcome_title']) ?></textarea>
                        <p class="liz-note">Use <code>&lt;em&gt;texto&lt;/em&gt;</code> para itálico dourado. Use <code>&lt;br&gt;</code> para quebra de linha.</p>
                    </div>
                </div>
                <div class="liz-row"><label>Parágrafo 1</label>
                    <input type="text" name="welcome_p1" value="<?= esc_attr($s['welcome_p1']) ?>"></div>
                <div class="liz-row"><label>Parágrafo 2</label>
                    <input type="text" name="welcome_p2" value="<?= esc_attr($s['welcome_p2']) ?>"></div>
                <div class="liz-row"><label>Texto do Botão</label>
                    <input type="text" name="welcome_btn" value="<?= esc_attr($s['welcome_btn']) ?>" style="max-width:220px"></div>
            </div>

            <!-- Campos de identificação -->
            <div class="liz-sec">
                <h2>📋 Campos de Identificação</h2>
                <p style="font-size:12px;color:#888;margin-bottom:14px">Ative/desative cada campo e personalize o texto da pergunta.</p>

                <?php foreach ( $id_fields as $f ) :
                    $on_key = 'f_' . $f['key'] . '_on'; ?>
                <div class="liz-field-row">
                    <div class="liz-field-head">
                        <label class="liz-toggle">
                            <input type="checkbox" name="<?= $on_key ?>" value="1" <?= checked( ! empty( $s[$on_key] ), true, false ) ?>>
                            <span class="liz-slider"></span>
                        </label>
                        <strong><?= esc_html( $f['label'] ) ?></strong>
                    </div>
                    <div class="liz-field-body">
                        <div>
                            <label>Texto da pergunta</label>
                            <textarea name="<?= esc_attr($f['q_key']) ?>"><?= esc_textarea($s[$f['q_key']]) ?></textarea>
                            <p class="liz-note">Use <code>&lt;em&gt;palavra&lt;/em&gt;</code> para destaque dourado.</p>
                        </div>
                        <?php if ( $f['sub_key'] ) : ?>
                        <div>
                            <label>Subtítulo (opcional)</label>
                            <input type="text" name="<?= esc_attr($f['sub_key']) ?>" value="<?= esc_attr($s[$f['sub_key']]) ?>">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Perguntas -->
            <div class="liz-sec">
                <h2>❓ Perguntas</h2>
                <p style="font-size:12px;color:#888;margin-bottom:14px">Ative/desative cada pergunta e edite o texto. As ativas aparecem em ordem no formulário.</p>

                <?php for ( $i = 1; $i <= 5; $i++ ) :
                    $on_key   = "q{$i}_on";
                    $text_key = "q{$i}_text"; ?>
                <div class="liz-field-row">
                    <div class="liz-field-head">
                        <label class="liz-toggle">
                            <input type="checkbox" name="<?= $on_key ?>" value="1" <?= checked( ! empty( $s[$on_key] ), true, false ) ?>>
                            <span class="liz-slider"></span>
                        </label>
                        <strong>Pergunta <?= $i ?></strong>
                    </div>
                    <div class="liz-field-body">
                        <div>
                            <label>Texto da pergunta</label>
                            <textarea name="<?= $text_key ?>"><?= esc_textarea($s[$text_key]) ?></textarea>
                            <p class="liz-note">Use <code>&lt;em&gt;palavra&lt;/em&gt;</code> para destaque dourado.</p>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Tela de sucesso -->
            <div class="liz-sec">
                <h2>✅ Tela de Sucesso</h2>
                <div class="liz-row"><label>Mensagem</label>
                    <div>
                        <textarea name="success_sub"><?= esc_textarea($s['success_sub']) ?></textarea>
                        <p class="liz-note">Use <code>&lt;br&gt;</code> para quebra de linha.</p>
                    </div>
                </div>
            </div>

            <p><button type="submit" class="button button-primary button-large">💾 Salvar Configurações</button></p>
        </form>
    </div>
    <?php
}

// ═══════════════════════════════════════════════════════
// SALVAR CONFIGURAÇÕES
// ═══════════════════════════════════════════════════════
add_action( 'admin_post_lizform_save', 'lizform_save_settings' );
function lizform_save_settings() {
    check_admin_referer( 'lizform_save' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Sem permissão.' );

    $rich = [ 'welcome_title', 'welcome_p1', 'welcome_p2',
              'f_nome_q', 'f_insta_q', 'f_email_q', 'f_cidade_q', 'f_idade_q', 'f_prof_q',
              'q1_text', 'q2_text', 'q3_text', 'q4_text', 'q5_text', 'success_sub' ];
    $on_keys = [ 'f_nome_on', 'f_insta_on', 'f_email_on', 'f_cidade_on',
                 'f_idade_on', 'f_prof_on', 'q1_on', 'q2_on', 'q3_on', 'q4_on', 'q5_on' ];

    $allowed = [ 'em' => [], 'strong' => [], 'br' => [] ];
    $new     = [];

    foreach ( lizform_defaults() as $k => $def ) {
        if ( in_array( $k, $on_keys, true ) ) {
            $new[$k] = isset( $_POST[$k] ) ? '1' : '';
        } elseif ( in_array( $k, $rich, true ) ) {
            $new[$k] = wp_kses( wp_unslash( $_POST[$k] ?? $def ), $allowed );
        } else {
            $new[$k] = sanitize_text_field( wp_unslash( $_POST[$k] ?? $def ) );
        }
    }

    update_option( 'liz_form_settings', $new );
    wp_redirect( admin_url( 'admin.php?page=liz-form-settings&saved=1' ) );
    exit;
}

// ═══════════════════════════════════════════════════════
// EXPORT CSV
// ═══════════════════════════════════════════════════════
function lizform_export_csv() {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    $rows = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'liz_submissions ORDER BY data_envio DESC', ARRAY_A );
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="inscricoes-' . date('Y-m-d') . '.csv"' );
    $f = fopen( 'php://output', 'w' );
    fprintf( $f, chr(0xEF).chr(0xBB).chr(0xBF) );
    fputcsv( $f, ['ID','Data','Nome','Instagram','E-mail','Cidade','Idade','Profissão','P1','P2','P3','P4','P5'], ';' );
    foreach ( $rows as $r ) {
        fputcsv( $f, [ $r['id'], $r['data_envio'], $r['nome'], $r['instagram'], $r['email'],
            $r['cidade'], $r['idade'], $r['profissao'],
            $r['pergunta_1'], $r['pergunta_2'], $r['pergunta_3'], $r['pergunta_4'], $r['pergunta_5'] ], ';' );
    }
    fclose( $f ); exit;
}

// ═══════════════════════════════════════════════════════
// REST API
// ═══════════════════════════════════════════════════════
add_action( 'rest_api_init', function() {
    register_rest_route( 'liz-form/v1', '/submit', [
        'methods'             => 'POST',
        'callback'            => 'lizform_api_submit',
        'permission_callback' => '__return_true',
    ]);
});

function lizform_api_submit( WP_REST_Request $req ) {
    global $wpdb;
    $d = $req->get_json_params();
    if ( empty($d['nome']) && empty($d['email']) ) {
        return new WP_Error( 'invalid', 'Dados insuficientes.', ['status'=>400] );
    }
    $ok = $wpdb->insert( $wpdb->prefix . 'liz_submissions', [
        'nome'       => sanitize_text_field( $d['nome']       ?? '' ),
        'instagram'  => sanitize_text_field( $d['instagram']  ?? '' ),
        'email'      => sanitize_email(      $d['email']      ?? '' ),
        'cidade'     => sanitize_text_field( $d['cidade']     ?? '' ),
        'idade'      => sanitize_text_field( $d['idade']      ?? '' ),
        'profissao'  => sanitize_text_field( $d['profissao']  ?? '' ),
        'pergunta_1' => sanitize_textarea_field( $d['pergunta_1'] ?? '' ),
        'pergunta_2' => sanitize_textarea_field( $d['pergunta_2'] ?? '' ),
        'pergunta_3' => sanitize_textarea_field( $d['pergunta_3'] ?? '' ),
        'pergunta_4' => sanitize_textarea_field( $d['pergunta_4'] ?? '' ),
        'pergunta_5' => sanitize_textarea_field( $d['pergunta_5'] ?? '' ),
    ]);
    if ( ! $ok ) return new WP_Error( 'db', 'Erro ao salvar.', ['status'=>500] );

    // ── Notificação por e-mail ───────────────────────────
    $s        = lizform_get();
    $nome     = sanitize_text_field( $d['nome']      ?? '' );
    $to       = 'laboratorio@lizmaria.com.br';
    $subject  = '✦ Nova inscrição — ' . wp_strip_all_tags( $s['welcome_tag'] ) . ' | ' . $nome;

    $q_labels = [];
    for ( $i = 1; $i <= 5; $i++ ) {
        if ( ! empty( $s["q{$i}_on"] ) ) {
            $q_labels[$i] = wp_strip_all_tags( $s["q{$i}_text"] );
        }
    }

    $body  = "Nova inscrição recebida via formulário.\n\n";
    $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $body .= "DADOS DE IDENTIFICAÇÃO\n";
    $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    if ( ! empty( $s['f_nome_on'] ) )   $body .= "Nome:       " . sanitize_text_field( $d['nome']      ?? '—' ) . "\n";
    if ( ! empty( $s['f_insta_on'] ) )  $body .= "Instagram:  " . sanitize_text_field( $d['instagram'] ?? '—' ) . "\n";
    if ( ! empty( $s['f_email_on'] ) )  $body .= "E-mail:     " . sanitize_email(      $d['email']     ?? '—' ) . "\n";
    if ( ! empty( $s['f_cidade_on'] ) ) $body .= "Cidade/UF:  " . sanitize_text_field( $d['cidade']    ?? '—' ) . "\n";
    if ( ! empty( $s['f_idade_on'] ) )  $body .= "Idade:      " . sanitize_text_field( $d['idade']     ?? '—' ) . "\n";
    if ( ! empty( $s['f_prof_on'] ) )   $body .= "Profissão:  " . sanitize_text_field( $d['profissao'] ?? '—' ) . "\n";

    if ( $q_labels ) {
        $body .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "RESPOSTAS\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        foreach ( $q_labels as $qi => $ql ) {
            $resp  = sanitize_textarea_field( $d['pergunta_' . $qi] ?? '—' );
            $body .= "\n[Pergunta {$qi}] {$ql}\n→ {$resp}\n";
        }
    }

    $body .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $body .= "Data: " . current_time( 'd/m/Y \à\s H:i' ) . "\n";
    $body .= "Ver no painel: " . admin_url( 'admin.php?page=liz-form' ) . "\n";

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . sanitize_email( $d['email'] ?? '' ),
    ];

    wp_mail( $to, $subject, $body, $headers );
    // ── /Notificação ─────────────────────────────────────

    return [ 'success' => true, 'id' => $wpdb->insert_id ];
}

// ═══════════════════════════════════════════════════════
// FRONTEND — injeta popup em todas as páginas
// ═══════════════════════════════════════════════════════
add_action( 'wp_footer', 'lizform_inject_popup' );

function lizform_inject_popup() {
    $s = lizform_get();

    // ── Constrói lista de steps ativos ──────────────────
    $steps = [];
    $steps[] = ['type' => 'welcome'];

    $id_defs = [
        ['key'=>'nome',   'input'=>'text',   'on'=>'f_nome_on',   'q'=>'f_nome_q',   'sub'=>'',          'ph'=>'Escreva seu nome aqui...', 'ac'=>'name',  'err'=>'Por favor, informe seu nome completo.',   'db'=>'nome',      'err_id'=>'lerr-nome'],
        ['key'=>'insta',  'input'=>'text',   'on'=>'f_insta_on',  'q'=>'f_insta_q',  'sub'=>'f_insta_sub','ph'=>'@seuperfil',              'ac'=>'',      'err'=>'Por favor, informe seu Instagram.',       'db'=>'instagram', 'err_id'=>'lerr-insta'],
        ['key'=>'email',  'input'=>'email',  'on'=>'f_email_on',  'q'=>'f_email_q',  'sub'=>'',          'ph'=>'seuemail@exemplo.com',     'ac'=>'email', 'err'=>'Por favor, informe um e-mail válido.',   'db'=>'email',     'err_id'=>'lerr-email'],
        ['key'=>'cidade', 'input'=>'text',   'on'=>'f_cidade_on', 'q'=>'f_cidade_q', 'sub'=>'',          'ph'=>'Ex: São Paulo, SP',        'ac'=>'',      'err'=>'Por favor, informe sua cidade e estado.','db'=>'cidade',    'err_id'=>'lerr-cidade'],
        ['key'=>'idade',  'input'=>'number', 'on'=>'f_idade_on',  'q'=>'f_idade_q',  'sub'=>'',          'ph'=>'Sua idade',                'ac'=>'',      'err'=>'Por favor, informe sua idade.',           'db'=>'idade',     'err_id'=>'lerr-idade'],
        ['key'=>'prof',   'input'=>'text',   'on'=>'f_prof_on',   'q'=>'f_prof_q',   'sub'=>'',          'ph'=>'Ex: Empreendedora, Terapeuta...','ac'=>'', 'err'=>'Por favor, informe sua profissão.',      'db'=>'profissao', 'err_id'=>'lerr-profissao'],
    ];

    $q_map = [];
    $field_num = 0;

    foreach ( $id_defs as &$f ) {
        if ( ! empty( $s[$f['on']] ) ) {
            $field_num++;
            $f['idx']     = count($steps);
            $f['num']     = $field_num;
            $steps[] = ['type'=>'field', 'def'=>$f];
        }
    }
    unset($f);

    $q_num = 0;
    for ( $qi = 1; $qi <= 5; $qi++ ) {
        if ( ! empty( $s["q{$qi}_on"] ) ) {
            $q_num++;
            $idx = count($steps);
            $q_map[$qi] = $idx;
            $steps[] = ['type'=>'question','num'=>$q_num,'qi'=>$qi,'text'=>$s["q{$qi}_text"],'idx'=>$idx,'err_id'=>'lerr-q'.$qi];
        }
    }

    $success_idx = count($steps);
    $steps[] = ['type'=>'success'];
    $total = $success_idx;

    // ── Validation map para JS ───────────────────────────
    $vmap = [];
    foreach ( $steps as $step ) {
        if ( $step['type'] === 'field' ) {
            $f = $step['def'];
            $vmap[$f['idx']] = ['el'=>'lf-'.$f['key'], 'err'=>$f['err_id'], 'type'=>$f['input']==='email'?'email':'text'];
        } elseif ( $step['type'] === 'question' ) {
            $vmap[$step['idx']] = ['el'=>'lf-q'.$step['qi'], 'err'=>$step['err_id'], 'type'=>'text'];
        }
    }

    $bg     = esc_attr( $s['bg_color'] );
    $ac     = esc_attr( $s['accent_color'] );
    $bt     = esc_attr( $s['btn_text_color'] );
    $cls    = esc_js(   $s['trigger_class'] );
    $rurl   = esc_js(   $s['redirect_url'] );
    $rdel   = (int)$s['redirect_delay'] * 1000;
    $apiurl = esc_js( rest_url('liz-form/v1/submit') );

    ?>
<!-- ── Liz Form v3 ───────────────────────────────────── -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
<style id="liz-form-style">
#liz-overlay,#liz-overlay *{box-sizing:border-box;margin:0;padding:0}

#liz-overlay{
  position:fixed;inset:0;z-index:999999;
  background:rgba(0,0,0,.88);
  display:flex;align-items:center;justify-content:center;padding:24px;
  opacity:0;visibility:hidden;pointer-events:none;
  transition:opacity .35s ease,visibility .35s ease;
}
#liz-overlay.liz-open{opacity:1;visibility:visible;pointer-events:all}

#liz-modal{
  position:relative;width:100%;max-width:720px;height:660px;
  background:<?= $bg ?>;overflow:hidden;
  border:1px solid rgba(255,224,109,.1);flex-shrink:0;
}

#liz-xbtn{
  position:absolute;top:16px;right:18px;z-index:50;
  background:transparent;border:none;
  color:rgba(255,255,255,.35);font-size:22px;line-height:1;
  cursor:pointer;padding:4px 6px;font-family:sans-serif;transition:color .2s;
}
#liz-xbtn:hover{color:<?= $ac ?>}

#liz-pbar{
  position:absolute;top:0;left:0;height:2px;
  background:<?= $ac ?>;width:0%;transition:width .6s ease;z-index:40;
}

.lf-orn{position:absolute;pointer-events:none;opacity:.04}

.lf-step{
  position:absolute;inset:0;
  display:flex;align-items:center;justify-content:center;
  padding:56px 60px 44px;overflow-y:auto;
  opacity:0;transform:translateY(36px);pointer-events:none;
  transition:opacity .5s ease,transform .5s ease;
}
.lf-step.active{opacity:1;transform:translateY(0);pointer-events:all}
.lf-step.exit{opacity:0;transform:translateY(-36px);pointer-events:none}

.lf-box{width:100%}

.lf-label{
  font-family:'Open Sans',sans-serif;font-size:11px;font-weight:600;
  letter-spacing:3px;text-transform:uppercase;
  color:<?= $ac ?>;opacity:.7;margin-bottom:12px;
}

.lf-box h2{
  font-family:'Playfair Display',serif;
  font-size:clamp(22px,2.8vw,36px);font-weight:600;line-height:1.25;
  color:#fff;margin-bottom:10px;
}
.lf-box h2 em{font-style:italic;color:<?= $ac ?>}

.lf-sub{
  font-family:'Open Sans',sans-serif;font-size:14px;
  color:rgba(255,255,255,.5);margin-bottom:28px;line-height:1.65;
}

.lf-input,.lf-textarea{
  display:block;width:100%;background:transparent;border:none;
  border-bottom:1.5px solid rgba(255,224,109,.3);
  color:#fff;font-family:'Open Sans',sans-serif;
  font-size:19px;padding:10px 0;outline:none;
  caret-color:<?= $ac ?>;transition:border-color .3s;
}
.lf-input:focus,.lf-textarea:focus{border-bottom-color:<?= $ac ?>}
.lf-input::placeholder,.lf-textarea::placeholder{color:rgba(255,255,255,.2);font-size:16px}
.lf-textarea{resize:none;min-height:90px;font-size:16px;line-height:1.7}

.lf-err{
  font-family:'Open Sans',sans-serif;font-size:12px;color:#ff7070;
  margin-top:6px;height:16px;opacity:0;transition:opacity .2s;
}
.lf-err.show{opacity:1}

.lf-actions{display:flex;align-items:center;gap:24px;margin-top:32px}

.lf-ok{
  display:inline-flex;align-items:center;gap:10px;
  background:<?= $ac ?>;color:<?= $bt ?>;border:none;
  padding:15px 36px;font-family:'Open Sans',sans-serif;
  font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase;
  cursor:pointer;transition:background .25s,transform .2s;
}
.lf-ok:hover{background:#fff;transform:translateY(-2px)}

.lf-back{
  background:transparent;border:none;color:rgba(255,255,255,.3);
  font-family:'Open Sans',sans-serif;font-size:11px;
  letter-spacing:1.5px;text-transform:uppercase;
  cursor:pointer;transition:color .25s;padding:12px 0;
}
.lf-back:hover{color:<?= $ac ?>}

.lf-hint{margin-top:12px;font-size:11px;color:rgba(255,255,255,.2);font-family:'Open Sans',sans-serif}
.lf-hint kbd{
  display:inline-block;background:rgba(255,255,255,.07);
  border:1px solid rgba(255,255,255,.12);border-radius:3px;
  padding:1px 5px;font-size:10px;color:rgba(255,255,255,.28);
}

.lf-tag{
  font-family:'Open Sans',sans-serif;font-size:10px;font-weight:600;
  letter-spacing:4px;text-transform:uppercase;
  color:<?= $ac ?>;opacity:.6;margin-bottom:18px;
}
#lf0 h1{
  font-family:'Playfair Display',serif;
  font-size:clamp(28px,3.4vw,48px);font-weight:700;line-height:1.15;
  color:#fff;margin-bottom:14px;
}
#lf0 h1 em{color:<?= $ac ?>;font-style:italic}
.lf-divider{width:36px;height:1.5px;background:<?= $ac ?>;opacity:.45;margin:14px 0}
#lf0 p{font-family:'Open Sans',sans-serif;font-size:14px;line-height:1.75;color:rgba(255,255,255,.52)}

.lf-start{
  display:inline-flex;align-items:center;gap:12px;
  background:<?= $ac ?>;color:<?= $bt ?>;border:none;
  padding:17px 48px;margin-top:32px;
  font-family:'Open Sans',sans-serif;font-size:11px;font-weight:700;
  letter-spacing:2.5px;text-transform:uppercase;
  cursor:pointer;transition:background .25s,transform .2s,box-shadow .25s;
}
.lf-start:hover{background:#fff;transform:translateY(-3px);box-shadow:0 12px 36px rgba(255,224,109,.15)}

.lf-icon{
  width:52px;height:52px;border:1.5px solid <?= $ac ?>;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:20px;color:<?= $ac ?>;margin-bottom:20px;
}
.lf-dots{display:flex;gap:8px;margin-top:24px}
.lf-dot{width:7px;height:7px;background:<?= $ac ?>;border-radius:50%;animation:lfpulse 1.5s infinite}
.lf-dot:nth-child(2){animation-delay:.25s}
.lf-dot:nth-child(3){animation-delay:.5s}
@keyframes lfpulse{0%,80%,100%{opacity:.22;transform:scale(.7)}40%{opacity:1;transform:scale(1)}}

@media(max-width:767px){
  #liz-overlay{padding:0;align-items:flex-end}
  #liz-modal{max-width:100%;height:auto;min-height:520px;max-height:92vh;border:none;border-top:1px solid rgba(255,224,109,.12);border-radius:0}
  .lf-step{padding:48px 28px 36px;align-items:flex-start}
  .lf-box h2{font-size:clamp(19px,5vw,26px);margin-bottom:8px}
  #lf0 h1{font-size:clamp(22px,7vw,32px);margin-bottom:10px}
  #lf0 p{font-size:13px}
  .lf-divider{margin:10px 0}
  .lf-tag{margin-bottom:12px}
  .lf-label{margin-bottom:8px}
  .lf-sub{margin-bottom:16px;font-size:13px}
  .lf-textarea{min-height:88px}
  .lf-input{font-size:17px}
  .lf-start{padding:16px 36px;margin-top:24px}
  .lf-actions{margin-top:28px;gap:20px}
  .lf-ok{padding:15px 32px}
  .lf-back{padding:14px 0;font-size:10px}
  .lf-hint{display:none}
}
</style>

<div id="liz-overlay">
  <div id="liz-modal">

    <button id="liz-xbtn" aria-label="Fechar">&#x2715;</button>
    <div id="liz-pbar"></div>

    <svg class="lf-orn" style="top:-140px;right:-140px;width:500px;height:500px;" viewBox="0 0 500 500" fill="none">
      <circle cx="250" cy="250" r="210" stroke="<?= $ac ?>" stroke-width="1"/>
      <circle cx="250" cy="250" r="145" stroke="<?= $ac ?>" stroke-width=".6"/>
      <circle cx="250" cy="250" r="80"  stroke="<?= $ac ?>" stroke-width=".4"/>
    </svg>
    <svg class="lf-orn" style="bottom:-70px;left:-70px;width:260px;height:260px;" viewBox="0 0 260 260" fill="none">
      <circle cx="130" cy="130" r="105" stroke="<?= $ac ?>" stroke-width=".6"/>
    </svg>

    <?php foreach ( $steps as $si => $step ) :
        $active = $si === 0 ? ' active' : '';
    ?>
    <div class="lf-step<?= $active ?>" id="lf<?= $si ?>">

    <?php if ( $step['type'] === 'welcome' ) : ?>
      <div class="lf-box">
        <div class="lf-tag"><?= wp_kses( $s['welcome_tag'], ['br'=>[]] ) ?></div>
        <h1><?= wp_kses( $s['welcome_title'], ['em'=>[],'br'=>[]] ) ?></h1>
        <div class="lf-divider"></div>
        <p><?= esc_html( $s['welcome_p1'] ) ?></p>
        <p style="margin-top:6px"><?= esc_html( $s['welcome_p2'] ) ?></p>
        <button class="lf-start" onclick="lfNext()">
          <?= esc_html( $s['welcome_btn'] ) ?>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </div>

    <?php elseif ( $step['type'] === 'field' ) :
        $f       = $step['def'];
        $num_pad = str_pad( $f['num'], 2, '0', STR_PAD_LEFT );
        $is_last = ( $si === $total );
    ?>
      <div class="lf-box">
        <div class="lf-label"><?= $num_pad ?> &nbsp;—</div>
        <h2><?= wp_kses( $s[$f['q']], ['em'=>[],'strong'=>[]] ) ?></h2>
        <?php if ( $f['sub'] && ! empty( $s[$f['sub']] ) ) : ?>
          <p class="lf-sub"><?= esc_html( $s[$f['sub']] ) ?></p>
        <?php endif; ?>
        <?php if ( $f['input'] === 'number' ) : ?>
          <input class="lf-input" type="number" id="lf-<?= $f['key'] ?>" placeholder="<?= esc_attr($f['ph']) ?>" min="1" max="120" <?= $f['ac'] ? 'autocomplete="'.$f['ac'].'"' : '' ?>>
        <?php else : ?>
          <input class="lf-input" type="<?= $f['input'] ?>" id="lf-<?= $f['key'] ?>" placeholder="<?= esc_attr($f['ph']) ?>" <?= $f['ac'] ? 'autocomplete="'.$f['ac'].'"' : '' ?>>
        <?php endif; ?>
        <div class="lf-err" id="<?= $f['err_id'] ?>"><?= esc_html( $f['err'] ) ?></div>
        <div class="lf-actions">
          <button class="lf-ok" onclick="lfNext()">
            <?= $is_last ? 'Enviar' : 'OK' ?>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <?php if ($is_last): ?><path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
              <?php else: ?><path d="M20 6L9 17l-5-5"/><?php endif; ?>
            </svg>
          </button>
          <button class="lf-back" onclick="lfBack()">← Voltar</button>
        </div>
        <div class="lf-hint"><kbd>Enter</kbd> para continuar</div>
      </div>

    <?php elseif ( $step['type'] === 'question' ) :
        $q_pad   = str_pad( $step['num'], 2, '0', STR_PAD_LEFT );
        $is_last = ( $si === $total );
    ?>
      <div class="lf-box">
        <div class="lf-label">Pergunta <?= $q_pad ?> &nbsp;—</div>
        <h2><?= wp_kses( $step['text'], ['em'=>[],'strong'=>[]] ) ?></h2>
        <textarea class="lf-textarea" id="lf-q<?= $step['qi'] ?>" placeholder="Escreva com liberdade..."></textarea>
        <div class="lf-err" id="<?= $step['err_id'] ?>">Por favor, responda a pergunta.</div>
        <div class="lf-actions">
          <button class="lf-ok" onclick="lfNext()">
            <?= $is_last ? 'Enviar' : 'OK' ?>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <?php if ($is_last): ?><path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z"/>
              <?php else: ?><path d="M20 6L9 17l-5-5"/><?php endif; ?>
            </svg>
          </button>
          <button class="lf-back" onclick="lfBack()">← Voltar</button>
        </div>
        <div class="lf-hint">
          <kbd>Shift</kbd>&nbsp;+&nbsp;<kbd>Enter</kbd> nova linha &nbsp;·&nbsp;
          <kbd>Enter</kbd> <?= $is_last ? 'enviar' : 'continuar' ?>
        </div>
      </div>

    <?php elseif ( $step['type'] === 'success' ) : ?>
      <div class="lf-box" style="display:flex;flex-direction:column;align-items:center;text-align:center">
        <div class="lf-icon">✦</div>
        <h2>Obrigada, <em id="lf-nome-ok">querida</em>.</h2>
        <p style="font-family:'Open Sans',sans-serif;font-size:14px;color:rgba(255,255,255,.5);margin-top:12px;line-height:1.7">
          <?= wp_kses( $s['success_sub'], ['br'=>[],'em'=>[]] ) ?>
        </p>
        <div class="lf-dots">
          <div class="lf-dot"></div><div class="lf-dot"></div><div class="lf-dot"></div>
        </div>
      </div>

    <?php endif; ?>
    </div><!-- /.lf-step -->
    <?php endforeach; ?>

  </div><!-- /#liz-modal -->
</div><!-- /#liz-overlay -->

<script id="liz-form-js">
(function(){
  'use strict';
  var cur=0, TOTAL=<?= $total ?>, SUCCESS=<?= $success_idx ?>;
  var API='<?= $apiurl ?>', RURL='<?= $rurl ?>', RDEL=<?= $rdel ?>;
  var TC='<?= $cls ?>';
  var vmap=<?= json_encode($vmap) ?>;
  var db_fields=<?= json_encode(
    array_reduce($steps, function($carry, $step){
      if ($step['type']==='field'){ $carry[$step['def']['key']]=$step['def']['db']; }
      return $carry;
    }, [])
  ) ?>;

  function g(id){return document.getElementById(id);}

  function validate(n){
    var v=vmap[n]; if(!v)return true;
    var el=g(v.el), er=g(v.err);
    var val=(el.value||'').trim();
    if(!val){er.classList.add('show');el.focus();return false;}
    if(v.type==='email'&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)){er.classList.add('show');el.focus();return false;}
    er.classList.remove('show');return true;
  }

  function clearErr(n){var v=vmap[n];if(v)g(v.err).classList.remove('show');}

  function setBar(n){g('liz-pbar').style.width=(n===0?0:Math.round(n/TOTAL*100))+'%';}

  function goTo(next){
    var c=g('lf'+cur), nx=g('lf'+next);
    c.classList.add('exit');c.classList.remove('active');
    setTimeout(function(){c.classList.remove('exit');},560);
    nx.classList.add('active');
    cur=next;setBar(cur);
    setTimeout(function(){var v=vmap[cur];if(v){var el=g(v.el);if(el)el.focus();}},320);
  }

  function reset(){
    document.querySelectorAll('.lf-step').forEach(function(s){s.classList.remove('active','exit');});
    g('lf0').classList.add('active');
    cur=0;setBar(0);
    Object.values(vmap).forEach(function(v){
      var el=g(v.el);if(el)el.value='';
      var er=g(v.err);if(er)er.classList.remove('show');
    });
    for(var qi=1;qi<=5;qi++){var ta=g('lf-q'+qi);if(ta)ta.value='';}
  }

  function submit(){
    goTo(SUCCESS);
    var nomeEl=g('lf-nome');
    var nome=nomeEl?nomeEl.value.trim():'';
    var nameOk=g('lf-nome-ok');
    if(nameOk)nameOk.textContent=nome.split(' ')[0]||'querida';

    var data={};
    for(var key in db_fields){var el=g('lf-'+key);data[db_fields[key]]=el?el.value.trim():'';}
    for(var qi=1;qi<=5;qi++){var ta=g('lf-q'+qi);data['pergunta_'+qi]=ta?ta.value.trim():'';}

    fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
      .catch(function(e){console.warn('[LizForm]',e);});

    if(RURL){setTimeout(function(){window.location.href=RURL;},RDEL);}
  }

  window.lfNext=function(){
    if(cur===0){goTo(1);return;}
    if(!validate(cur))return;
    if(cur===TOTAL){submit();return;}
    goTo(cur+1);
  };

  window.lfBack=function(){
    if(cur<=1){goTo(0);return;}
    clearErr(cur);
    var c=g('lf'+cur), p=g('lf'+(cur-1));
    c.classList.remove('active');
    p.style.cssText+='opacity:0;transform:translateY(-36px);';
    p.classList.add('active');
    requestAnimationFrame(function(){requestAnimationFrame(function(){p.style.opacity='';p.style.transform='';});});
    cur--;setBar(cur);
  };

  document.addEventListener('keydown',function(e){
    if(!g('liz-overlay').classList.contains('liz-open'))return;
    if(e.key==='Escape'){closeLizModal();return;}
    if(e.key==='Enter'&&e.target.tagName==='INPUT'){e.preventDefault();window.lfNext();return;}
    if(e.key==='Enter'&&!e.shiftKey&&e.target.tagName==='TEXTAREA'){e.preventDefault();window.lfNext();return;}
    var v=vmap[cur];if(v&&g(v.err))g(v.err).classList.remove('show');
  });

  function openLizModal(){g('liz-overlay').classList.add('liz-open');document.body.style.overflow='hidden';}
  function closeLizModal(){g('liz-overlay').classList.remove('liz-open');document.body.style.overflow='';setTimeout(reset,400);}

  g('liz-xbtn').addEventListener('click',closeLizModal);
  g('liz-overlay').addEventListener('click',function(e){if(e.target===this)closeLizModal();});

  document.addEventListener('click',function(e){
    if(e.target.closest('.'+TC)){e.preventDefault();openLizModal();}
  });

  setBar(0);
}());
</script>
<!-- ── /Liz Form v3 ──────────────────────────────────── -->
    <?php
}
