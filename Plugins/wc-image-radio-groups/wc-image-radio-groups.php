<?php
/**
 * Plugin Name: WC Image Radio Groups Multi - Full Astra Compatible
 * Description: Image-radio opties voor WooCommerce met prijsverhoging, compact layout, Astra-ready, correct handling disabled options. Kan ook normale tekstradio's weergeven.
 * Version: 6.1 // Versie aangepast na de fix
 * Author: Jouw Naam
 * Text Domain: wc-image-radio-groups-multi
 */

if (!defined('ABSPATH'))
    exit;

// ================= Admin Assets =================
function wc_iro_enqueue_admin_assets()
{
    // Note: 'assets/css/style.css' and 'assets/js/script.js' are referenced but not included here.
    // Ensure these files exist in your plugin structure.
    wp_enqueue_style('wc-iro-style', plugin_dir_url(__FILE__) . 'assets/css/style.css');
    wp_enqueue_media();
    wp_enqueue_script('wc-iro-script', plugin_dir_url(__FILE__) . 'assets/js/script.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'wc_iro_enqueue_admin_assets');

function wc_iro_enqueue_admin_scripts($hook)
{
    global $post;

    // Laad alleen op de product bewerkpagina's (post.php of post-new.php)
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        // En controleer of we met een product bezig zijn
        if (isset($post) && 'product' === $post->post_type) {

            // 1. Cruciaal: Zorgt dat de WordPress media scripts en stijlen worden geladen
            wp_enqueue_media();

            // 2. Registreert en enqueuet jouw custom script
            // LET OP HET PAD! Zorg dat admin-media.js in dezelfde map zit als dit PHP-bestand
            wp_enqueue_script(
                'wc-iro-admin-media',
                plugin_dir_url(__FILE__) . 'admin-media.js',
                array('jquery'),
                '1.0',
                true
            );
        }
    }
}
// Koppel de functie aan de juiste WordPress actie
add_action('admin_enqueue_scripts', 'wc_iro_enqueue_admin_scripts');

// ================= Taxonomy =================
function wc_iro_register_group_taxonomy()
{
    $labels = array(
        'name' => 'Afbeeldingsgroepen',
        'singular_name' => 'Afbeeldingsgroep',
        'search_items' => 'Zoek groepen',
        'all_items' => 'Alle groepen',
        'edit_item' => 'Bewerk groep',
        'update_item' => 'Update groep',
        'add_new_item' => 'Nieuwe groep toevoegen',
        'new_item_name' => 'Nieuwe groepsnaam',
        'menu_name' => 'Afbeeldingsgroepen',
    );
    register_taxonomy('iro_group_assignment', 'product', array(
        'labels' => $labels,
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'iro-group')
    ));
}
add_action('init', 'wc_iro_register_group_taxonomy');


function wc_iro_add_controller_specific_fields()
{
    global $post;
    $product_id = $post->ID;

    // Haal de gekoppelde Afbeeldingsgroep(en) op
    $groups = wp_get_post_terms($product_id, 'iro_group_assignment');
    if (empty($groups)) {
        echo '<div class="options_group show_if_simple show_if_variable"><p>Koppel dit product eerst aan een Afbeeldingsgroep om overrides in te stellen.</p></div>';
        return;
    }

    // Haal de reeds opgeslagen overrides op (indien aanwezig)
    $overrides = get_post_meta($product_id, '_wc_iro_option_overrides', true);
    if (!is_array($overrides))
        $overrides = [];

    echo '<div class="options_group show_if_simple show_if_variable">';
    echo '<h3>Optie Overrides (Overschrijft Groepsinstellingen)</h3>';

    foreach ($groups as $group) {
        $options = get_term_meta($group->term_id, '_iro_options', true);
        if (empty($options))
            continue;

        echo '<h4 style="margin-top: 20px;">Groep: ' . esc_html($group->name) . '</h4>';

        foreach ($options as $i => $option) {
            $option_uid = $group->term_id . '_' . $i; // Unieke ID: bijv. 123_0
            $current_override = $overrides[$option_uid] ?? [];

            // De POST naam (gebruikt vierkante haken, bijv. wc_iro_override[123_0])
            $field_name_base = "wc_iro_override[{$option_uid}]";

            // De VEILIGE HTML ID (gebruikt door woocommerce_wp_text_input)
            $safe_field_id_base = str_replace(array('[', ']'), '_', $field_name_base);

            echo '<div style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px;">';
            echo '<strong>Optie: ' . esc_html($option['label']) . '</strong><br>';

            // Haal de opgeslagen waarde op, default naar 'no'
            $current_hide_value = $current_override['hide'] ?? 'no';

            // 1. Verberg Optie (Checkbox FIXED)
            echo '<p class="form-field ' . esc_attr($safe_field_id_base) . '_hide_field">';
            echo '<label for="' . esc_attr($safe_field_id_base) . '_hide">' . __('Verberg deze optie', 'text-domain') . '</label>';
            echo '<input type="checkbox" id="' . esc_attr($safe_field_id_base) . '_hide" name="' . esc_attr($field_name_base) . '[hide]" value="yes" ' . checked($current_hide_value, 'yes', false) . ' />';
            echo '<span class="description">' . __('Verberg deze optie op DIT product.', 'text-domain') . '</span>';
            echo '</p>';

            // 2. Proxy ID Override
            woocommerce_wp_text_input(array(
                // Cruciaal: Gebruik de veilige ID hier voor het HTML ID attribuut
                'id' => $safe_field_id_base . '_proxy_id',
                // Cruciaal: Gebruik de oorspronkelijke naam voor het POST veld
                'name' => $field_name_base . '[proxy_id]',
                'label' => __('Proxy ID Override', 'text-domain'),
                'placeholder' => 'Huidige Groeps-ID: ' . esc_attr($option['proxy_id'] ?? 'Geen'),
                'value' => $current_override['proxy_id'] ?? '',
                'description' => __('Laat leeg om de Groeps-ID te erven.', 'text-domain')
            ));

            // 3. Afbeelding ID Override met Media Uploader

            $img_id_val = $current_override['img_id'] ?? '';
            $img_url = $img_id_val ? wp_get_attachment_image_url($img_id_val, 'thumbnail') : '';
            $hide_image_wrap = empty($img_url) ? 'style="display: none;"' : '';

            echo '<p class="form-field ' . esc_attr($safe_field_id_base) . '_img_id_field">';
            echo '<label>' . __('Afbeelding ID Override', 'text-domain') . '</label>';

            echo '<span class="description">' . __('Laat leeg om de Groepsafbeelding te erven (Media ID).', 'text-domain') . '</span>';

            // Afbeelding Preview en Verwijderknop
            echo '<div class="wc-iro-media-preview-wrap" ' . $hide_image_wrap . '>';
            echo '<img src="' . esc_url($img_url) . '" class="wc-iro-media-preview" style="max-width:100px; height:auto; display:block; margin: 5px 0;" />';
            echo '<button type="button" class="button wc-iro-remove-image-button">Verwijder afbeelding</button>';
            echo '</div>';

            // Verborgen Tekstveld voor de ID (Gebruik veilige ID voor het ID-attribuut)
            echo '<input type="hidden" class="wc-iro-media-id-field" id="' . esc_attr($safe_field_id_base) . '_img_id" name="' . esc_attr($field_name_base) . '[img_id]" value="' . esc_attr($img_id_val) . '" />';

            // De Selectieknop
            echo '<button type="button" class="button wc-iro-select-image-button ' . (empty($img_url) ? '' : 'hidden') . '">Selecteer Afbeelding</button>';

            echo '<span class="description">Huidige Groeps Media ID: ' . esc_attr($option['img_id'] ?? 'Geen') . '</span>';

            echo '</p>';

            echo '</div>';
        }
    }

    echo '</div>';
}
add_action('woocommerce_product_options_inventory_product_data', 'wc_iro_add_controller_specific_fields');

function wc_iro_save_controller_specific_fields($post_id)
{
    // Controleer of de POST data array bestaat
    if (isset($_POST['wc_iro_override']) && is_array($_POST['wc_iro_override'])) {
        $data_to_save = [];

        foreach ($_POST['wc_iro_override'] as $uid => $fields) {

            // Bepaal de 'hide' status:
            // Als 'hide' in de ingediende velden zit, dan is deze aangevinkt ('yes').
            // Zo niet, dan is deze NIET aangevinkt ('no').
            $hide_status = isset($fields['hide']) ? 'yes' : 'no';

            // We slaan alleen de override op als er data is ingevuld OF als de checkbox actief (aangevinkt) is.
            if (
                !empty($fields['proxy_id']) ||
                !empty($fields['img_id']) ||
                $hide_status === 'yes' // Sla op als deze is aangevinkt
            ) {
                $data_to_save[$uid] = [
                    // De CORRECTE opslag van de checkbox status
                    'hide' => $hide_status,
                    'proxy_id' => sanitize_text_field($fields['proxy_id'] ?? ''),
                    'img_id' => sanitize_text_field($fields['img_id'] ?? ''),
                ];
            } else if (empty($fields['proxy_id']) && empty($fields['img_id']) && $hide_status === 'no') {
                // Als de status 'no' is EN de andere velden zijn leeg, verwijderen we de override (niets opslaan).
                continue;
            }
        }

        // Update de meta
        update_post_meta($post_id, '_wc_iro_option_overrides', $data_to_save);
    } else {
        // Zorg dat de meta-key wordt verwijderd/geleegd als er geen overrides zijn
        delete_post_meta($post_id, '_wc_iro_option_overrides');
    }
}
add_action('woocommerce_process_product_meta', 'wc_iro_save_controller_specific_fields');


function wc_iro_add_group_options_meta_box($term)
{
    $term_id = isset($term->term_id) ? $term->term_id : 0;
    $options = get_term_meta($term_id, '_iro_options', true);
    if (!is_array($options))
        $options = array();
    ?>
    <div id="iro-options-wrapper">
        <button type="button" class="button" id="iro-add-option">Nieuwe optie toevoegen</button>
        <ul id="iro-options-list">
            <?php foreach ($options as $i => $option):
                $isDisabled = !empty($option['disabled']);
                ?>
                <li>
                    <input type="text" name="iro_options[label][]" value="<?php echo esc_attr($option['label']); ?>"
                        placeholder="Label">
                    <input type="number" step="0.01" name="iro_options[price][]"
                        value="<?php echo esc_attr($option['price']); ?>" placeholder="Prijs">

                    <p>
                        <label for="wc_iro_option_proxy_id_<?php echo $i; ?>">Gekoppelde Proxy Product ID (voor
                            voorraad):</label>
                        <input type="text" id="wc_iro_option_proxy_id_<?php echo $i; ?>" name="iro_options[proxy_id][]"
                            value="<?php echo esc_attr($option['proxy_id'] ?? ''); ?>"
                            placeholder="Laat leeg of voer een Product ID in" style="width: 100%;" />
                    </p>

                    <p>
                        <label for="wc_iro_option_img_id_<?php echo $i; ?>">Media ID (voor afbeelding overschrijven):</label>
                        <input type="text" id="wc_iro_option_img_id_<?php echo $i; ?>" name="iro_options[img_id][]"
                            value="<?php echo esc_attr($option['img_id'] ?? ''); ?>"
                            placeholder="Laat leeg of voer een Media ID in" style="width: 100%;" />
                        <span class="description">Voer een Media Bibliotheek ID in om de afbeelding URL hieronder te
                            overschrijven.</span>
                    </p>

                    <input type="text" class="iro-image-field" name="iro_options[image][]"
                        value="<?php echo esc_attr($option['image']); ?>">
                    <button class="button iro-upload-image">Kies afbeelding</button>
                    <button type="button" class="button iro-clear-image">Afbeelding wissen</button>
                    <img class="iro-image-preview" src="<?php echo esc_attr($option['image']); ?>"
                        style="max-width:80px; max-height:80px; display:<?php echo empty($option['image']) ? 'none' : 'block'; ?>; margin-top:2px;">
                    <label>Uitgeschakeld
                        <input type="checkbox" name="iro_options[disabled][]" value="<?php echo esc_attr($i); ?>" <?php checked($isDisabled); ?>>
                    </label>
                    <button type="button" class="button iro-remove-option">Verwijderen</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <script>
        // ... (De Javascript code blijft hetzelfde, behalve dat de nieuwe velden worden meegenomen)
        jQuery(document).ready(function ($) {
            var file_frame;

            $('#iro-add-option').on('click', function () {
                var newIndex = $('#iro-options-list li').length; // Use length for new index
                var newLi = '<li>' +
                    '<input type="text" name="iro_options[label][]" placeholder="Label"> ' +
                    '<input type="number" step="0.01" name="iro_options[price][]" placeholder="Prijs">' +
                    '<p>' +
                    '<label for="wc_iro_option_proxy_id_' + newIndex + '">Gekoppelde Proxy Product ID (voor voorraad):</label>' +
                    '<input type="text" id="wc_iro_option_proxy_id_' + newIndex + '" name="iro_options[proxy_id][]" placeholder="Laat leeg of voer een Product ID in" style="width: 100%;" />' +
                    '</p>' +
                    '<p>' +
                    '<label for="wc_iro_option_img_id_' + newIndex + '">Media ID (voor afbeelding overschrijven):</label>' +
                    '<input type="text" id="wc_iro_option_img_id_' + newIndex + '" name="iro_options[img_id][]" placeholder="Laat leeg of voer een Media ID in" style="width: 100%;" />' +
                    '<span class="description">Voer een Media Bibliotheek ID in om de afbeelding URL hieronder te overschrijven.</span>' +
                    '</p>' +
                    '<input type="text" class="iro-image-field" name="iro_options[image][]">' +
                    '<button class="button iro-upload-image">Kies afbeelding</button>' +
                    '<button type="button" class="button iro-clear-image">Afbeelding wissen</button>' +
                    '<img class="iro-image-preview" style="max-width:80px; max-height:80px; display:none; margin-top:2px;">' +
                    '<label>Uitgeschakeld <input type="checkbox" name="iro_options[disabled][]" value="' + newIndex + '"></label>' +
                    '<button type="button" class="button iro-remove-option">Verwijderen</button>' +
                    '</li>';
                $('#iro-options-list').append(newLi);
            });

            $(document).on('click', '.iro-remove-option', function () {
                $(this).parent().remove();
                // Re-map disabled values after removal
                $('#iro-options-list li').each(function (index) {
                    $(this).find('input[name="iro_options[disabled][]"]').val(index);
                });
            });

            $(document).on('click', '.iro-clear-image', function () {
                var button = $(this);
                var input = button.prevAll('.iro-image-field').first();
                var preview = button.next('.iro-image-preview');

                input.val('');
                preview.attr('src', '').hide();
            });

            $(document).on('click', '.iro-upload-image', function (e) {
                e.preventDefault();
                var button = $(this);
                var input = button.prev('.iro-image-field');
                var preview = button.nextAll('.iro-image-preview').first();

                file_frame = wp.media.frames.file_frame = wp.media({
                    title: 'Kies of upload een afbeelding',
                    button: { text: 'Kies afbeelding' },
                    multiple: false
                });

                file_frame.on('select', function () {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    input.val(attachment.url);
                    preview.attr('src', attachment.url).show();
                });

                file_frame.open();
            });
        });
    </script>
    <?php
}
add_action('iro_group_assignment_edit_form_fields', 'wc_iro_add_group_options_meta_box', 10, 2);
add_action('iro_group_assignment_add_form_fields', 'wc_iro_add_group_options_meta_box', 10, 2);

// ================= Save Options =================
function wc_iro_save_group_options($term_id)
{
    // Controleer of de formulierdata is ingediend en niet leeg is
    if (!isset($_POST['iro_options']['label']) || empty($_POST['iro_options']['label'])) {
        // Als er geen labels zijn ingediend, wissen we de opgeslagen opties.
        delete_term_meta($term_id, '_iro_options');
        return;
    }

    $labels = (array) $_POST['iro_options']['label'];
    $prices = (array) $_POST['iro_options']['price'];
    $images = (array) $_POST['iro_options']['image'];
    $proxy_ids = (array) $_POST['iro_options']['proxy_id'];
    $img_ids = (array) $_POST['iro_options']['img_id'];

    // Deze array's hebben alleen de indexen van de aangevinkte checkboxen
    $disableds = isset($_POST['iro_options']['disabled']) ? (array) $_POST['iro_options']['disabled'] : array();
    $disableds_map = array_map('intval', $disableds);

    $options = [];

    // We itereren over de labels (de meest betrouwbare veld) en gebruiken de index ($i)
    // om alle andere gerelateerde velden op te halen.
    foreach ($labels as $i => $label) {

        // Sla alleen op als er een label is (om lege velden na een verwijderactie te negeren)
        if (trim($label) !== '') {

            // Controleer op de index in de andere arrays. Als de array niet de index heeft, gebruik dan een fallback
            $current_price = $prices[$i] ?? 0;
            $current_image = $images[$i] ?? '';
            $current_proxy_id = $proxy_ids[$i] ?? '';
            $current_img_id = $img_ids[$i] ?? '';

            $options[] = array(
                'label' => sanitize_text_field($label),
                'price' => floatval($current_price),
                'image' => esc_url_raw($current_image),
                'proxy_id' => sanitize_text_field($current_proxy_id),
                'img_id' => sanitize_text_field($current_img_id),
                // Controleer of de index van deze optie ($i) in de map met uitgeschakelde indexen staat
                'disabled' => in_array($i, $disableds_map) ? 'yes' : '',
            );
        }
    }

    // Sla de volledig geconstrueerde en veilige optie-array op
    update_term_meta($term_id, '_iro_options', $options);
}
add_action('edited_iro_group_assignment', 'wc_iro_save_group_options', 10, 2);
add_action('created_iro_group_assignment', 'wc_iro_save_group_options', 10, 2);

// ================= Frontend Assets =================

/**
 * Dwingt een cart fragment refresh af wanneer de Astra minicart-trigger wordt geklikt.
 * Dit is nodig om aangepaste prijzen direct na toevoegen/openen correct te tonen.
 */
function wc_iro_refresh_on_minicart_open_astra_fix() {
    if (is_product() || is_front_page() || is_shop() || is_cart() || is_checkout()) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // De Astra-specifieke selector voor de minicart-knop, inclusief de container die je gaf
                // We luisteren naar een klik op het icoon of de telling/prijs.
                var minicart_trigger = $(
                    '.ast-site-header-cart, ' + // De algemene container
                    '.ast-cart-menu-wrap, ' +    // De wrapper
                    '.cart-contents'             // De standaard WooCommerce/Astra inhoud link
                );
                
                minicart_trigger.on('click', function(e) {
                    // Triggert de update van alle WooCommerce fragmenten (inclusief minicart)
                    // Let op: Sommige Astra-implementaties gebruiken 'wc_fragment_refresh'
                    // maar het 'click'-event triggert dit soms al.
                    // Het forceren is de veiligste methode:
                    $(document.body).trigger('wc_fragment_refresh');
                    
                    // console.log('Astra Mini-cart click gedetecteerd. Fragment refresh getriggerd.');
                });
            });
        </script>
        <?php
    }
}
// Gebruik een hoge prioriteit om zeker te zijn dat dit script na jQuery laadt
add_action('wp_footer', 'wc_iro_refresh_on_minicart_open_astra_fix', 999);

function wc_iro_enqueue_frontend_assets()
{
    if (is_product()) {
        // Aangezien je momenteel de CSS inline hebt, is dit deel nu optioneel.
        // Als je het CSS-bestand (frontend.css) gaat gebruiken, laat je dit staan:
        wp_enqueue_style('wc-iro-frontend', plugin_dir_url(__FILE__) . 'assets/css/frontend.css');
        wp_enqueue_script('wc-iro-frontend', plugin_dir_url(__FILE__) . 'assets/js/frontend.js', array('jquery'), null, true);
    }
}
// Door de prioriteit te verhogen naar 99, zorgen we dat de CSS van deze plugin 
// NA de meeste thema-stijlen (die standaard op 10 laden) wordt geladen.
add_action('wp_enqueue_scripts', 'wc_iro_enqueue_frontend_assets', 99);

// ================= Display Options Frontend =================
/**
 * Definitieve correctie van de prijsweergave in de mini-cart en winkelwagen.
 * Dit filter overschrijft de geformatteerde HTML prijs om de aanpassing te tonen.
 */
function wc_iro_final_cart_item_price_fix($price_html, $cart_item, $cart_item_key) {
    
    // Controleer of onze prijsaanpassing aanwezig is
    if (isset($cart_item['iro_price_adjustment']) && $cart_item['iro_price_adjustment'] > 0) {
        
        $adjustment = floatval($cart_item['iro_price_adjustment']);
        $product = $cart_item['data'];
        
        // Haal de BASE prijs van het product op (zonder eerdere filters of aanpassingen)
        // Dit is nodig omdat de 'set_price' die we eerder deden, niet altijd vasthoudt in de mini-cart context.
        $base_price = $product->get_regular_price('edit');
        
        // Bereken de NIEUWE prijs: Base prijs + Onze aanpassing
        $new_price = $base_price + $adjustment;

        // Vraag aan WooCommerce om de prijs inclusief of exclusief belasting te berekenen
        if (WC()->cart->display_prices_including_tax()) {
            // Gebruik wc_get_price_including_tax voor de belastingberekening
            $final_price = wc_get_price_including_tax($product, array('price' => $new_price));
        } else {
            // Gebruik wc_get_price_excluding_tax voor de belastingberekening
            $final_price = wc_get_price_excluding_tax($product, array('price' => $new_price));
        }
        
        // Retourneer de correct geformatteerde prijs string
        return wc_price($final_price);
    }
    
    return $price_html;
}
// Gebruik dit filter met een hoge prioriteit om zeker te zijn dat we de prijs vastleggen
add_filter('woocommerce_cart_item_price', 'wc_iro_final_cart_item_price_fix', 99, 3);

function wc_iro_display_image_options()
{
    global $product;

    $groups = wp_get_post_terms($product->get_id(), 'iro_group_assignment');
    if (empty($groups))
        return;

    // Haal alle product-specifieke overrides op
    $product_overrides = get_post_meta($product->get_id(), '_wc_iro_option_overrides', true);
    if (!is_array($product_overrides))
        $product_overrides = [];

    echo '<div class="wc-iro-options-wrapper">';

    foreach ($groups as $group) {
        $options = get_term_meta($group->term_id, '_iro_options', true);
        if (!empty($options)) {

            // NIEUW: Vlag om de eerste geselecteerde optie per groep bij te houden
            $first_active_selected = false;

            echo '<div class="wc-iro-group">';
            echo '<p class="wc-iro-group-title">' . esc_html($group->name) . '</p>';

            echo '<div class="wc-iro-options">';

            $activeIndex = 0;
            foreach ($options as $i => $option) {

                // Variabelen van de Optie Groep (Standaardwaarden/Erfenis)
                $isDisabled = !empty($option['disabled']);
                $price = floatval($option['price']);
                $label = esc_html($option['label']);
                $image = esc_url($option['image']);
                $has_image = !empty($image);

                // UID om de override data op te halen
                $option_uid = $group->term_id . '_' . $i;
                $override = $product_overrides[$option_uid] ?? [];

                // ===============================================
                // STAP 3: TOEPASSEN VAN DYNAMISCHE OVERRIDES
                // ===============================================

                // 1. Verbergen Override
                if (($override['hide'] ?? 'no') === 'yes') {
                    continue; // Slaat deze optie over en gaat naar de volgende
                }

                // 2. Proxy ID Override
                $proxy_product_id_final = intval($option['proxy_id'] ?? 0);
                if (!empty($override['proxy_id'])) {
                    $proxy_product_id_final = intval($override['proxy_id']);
                }

                // 3. Afbeelding Override
                $img_id_final = intval($option['img_id'] ?? 0);
                if (!empty($override['img_id'])) {
                    $img_id_final = intval($override['img_id']);
                }

                if ($img_id_final) {
                    $new_image_url = wp_get_attachment_image_url($img_id_final, 'thumbnail');
                    if (!empty($new_image_url)) {
                        $image = $new_image_url;
                        $has_image = true;
                    }
                }

                // ===============================================
                // STAP 4: VOORRAAD/NABESTELLING LOGICA (GEFORCEERD)
                // ===============================================

                $stock_label = '';
                $stock_status = 'in-stock'; // Standaard instock, tenzij overschreven
                $original_is_disabled = $isDisabled;

                if ($proxy_product_id_final) {
                    $proxy_product = wc_get_product($proxy_product_id_final);

                    if ($proxy_product) {

                        // Gebruik get_stock_quantity() in plaats van is_in_stock() voor zekerheid
                        $stock_qty = $proxy_product->get_stock_quantity();
                        $is_actually_in_stock = $stock_qty > 0;

                        if (!$is_actually_in_stock) {
                            // Product is NIET op voorraad. Check Nabestellingen.

                            if ($proxy_product->backorders_allowed()) {
                                // Nabestelling toegestaan
                                $stock_label = ' (Nabestelling)';
                                $isDisabled = false;
                                $stock_status = 'backorder';

                            } else {
                                // Uitverkocht
                                $isDisabled = true;
                                $stock_label = ' (Uitverkocht)';
                                $stock_status = 'sold-out';
                            }
                        } else {
                            // Product is op voorraad (voorraad > 0)
                            $stock_label = ' (Op voorraad)';
                            $stock_status = 'in-stock';
                            $isDisabled = $original_is_disabled;
                        }
                    }
                } else {
                    // Geen Proxy ID
                    if ($isDisabled) {
                        $stock_status = 'sold-out';
                    } else {
                        $stock_status = 'in-stock';
                        $stock_label = '';
                    }
                }

                // ===============================================
                // STAP 5: RENDERING (GECORRIGEERD VOOR EERSTE SELECTIE)
                // ===============================================

                // Finaliseer de Klassen en Attributen
                $disabled_class = $isDisabled ? ' wc-iro-option-disabled' : '';
                $class = 'wc-iro-option' . $disabled_class . ($has_image ? '' : ' wc-iro-no-image');
                $disabled_attr = $isDisabled ? ' disabled="disabled"' : '';
                $option_value = $isDisabled ? -1 : $activeIndex;
                $data_status = $stock_status; // Gebruik de correct ingestelde status

                // NIEUW: Controleer of dit de eerste beschikbare optie in de groep is
                $checked_attr = '';
                if (!$isDisabled && !$first_active_selected) {
                    $checked_attr = ' checked="checked"';
                    $first_active_selected = true; // Markeer dat de eerste is geselecteerd
                }


                // Start het renduren
                echo '<label class="' . $class . '" data-price="' . esc_attr($price) . '" data-stock-status="' . $data_status . '">';

                // Radio button
                echo '<input type="radio" name="iro_option_' . esc_attr($group->term_id) . '" value="' . esc_attr($option_value) . '"' . $disabled_attr . $checked_attr . '>';

                // Image wrap
                if ($has_image) {
                    echo '<div class="wc-iro-image-wrap"><img src="' . $image . '" alt="' . $label . '"></div>';
                }

                // === LABEL WRAPPER VOOR TEKST ===
                echo '<div class="wc-iro-label-wrap">';

                // HET OORSPRONKELIJKE LABEL EN PRIJS
                echo '<span class="wc-iro-label">' . $label . '</span>';

                // Price
                if ($price > 0) {
                    echo '<span class="wc-iro-price">(+€' . number_format($price, 2, ',', '') . ')</span>';
                }

                // VOORRAADSTATUS OP EEN NIEUWE REGEL
                if (!empty($stock_label)) {
                    echo '<span class="wc-iro-stock-status ' . esc_attr($stock_status) . '">' . esc_html($stock_label) . '</span>';
                }

                echo '</div>';
                // ========================================

                echo '</label>'; // Einde van de label

                // Verhoog de teller alleen voor actieve opties
                if (!$isDisabled) {
                    $activeIndex++;
                }
            } // Einde van foreach ($options as $i => $option)

            echo '</div></div>';
        }
    } // Einde van foreach ($groups as $group)

    echo '</div>'; // wc-iro-options-wrapper

    // Summary box
    echo '<div id="wc-iro-summary"><strong>Prijs overzicht:</strong><ul class="wc-iro-summary-list"></ul></div>';

    // Inline CSS + JS
    ?>
    <style>
        /* NIEUWE FIX: Forceer de optie-wrapper om de volledige breedte in te nemen 
           en op een nieuwe regel te beginnen na de knop. */
        .wc-iro-options-wrapper {
            /* Dwingt het element om zich als een blok te gedragen (start nieuwe regel) */
            display: block !important;

            /* Zorgt ervoor dat het de volledige beschikbare breedte inneemt */
            width: 100% !important;

            /* Zorgt dat het niet meezweeft met de knop */
            clear: both !important;

            /* Ruimte tussen de knop en de opties */
            margin-top: 15px !important;
        }

        /* Zorg dat de summary box er ook onder komt */
        #wc-iro-summary {
            display: block !important;
            width: 100% !important;
        }

        /* 1. Algemene Opmaak */
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-group>.wc-iro-group-title {
            font-weight: 700 !important;
            margin-top: 0 !important;
            margin-bottom: 4px !important;
        }

        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-group {
            margin-bottom: 10px !important;
        }

        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-options {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 10px !important;
            margin-top: 0 !important;
        }

        /* 2. Basis Radio/Optie Styling */
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-option {
            cursor: pointer;
            display: flex;
            position: relative;
            transition: all 0.2s ease !important;
        }

        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-option input[type=radio] {
            position: absolute;
            left: -9999px;
        }

        /* 3. Styling voor GEDEACTIVEERDE opties */
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-option-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* 4. Styling voor AFBEELDING-opties */
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-option:not(.wc-iro-no-image) {
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 4px !important;
            min-width: 80px;
        }

        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-image-wrap {
            width: 80px !important;
            height: 80px !important;
            border: 2px solid #ccc !important;
            border-radius: 10px !important;
            overflow: hidden !important;
            transition: all 0.2s ease !important;
        }

        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-image-wrap img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block !important;
        }

        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-option:hover .wc-iro-image-wrap {
            border-color: #888 !important;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2) !important;
        }

        /* 4b. Highlight voor AFBEELDING: Alleen de image-wrap krijgt de highlight */
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-option input[type=radio]:checked+.wc-iro-image-wrap {
            border-color: #0071a1 !important;
            box-shadow: 0 0 8px rgba(0, 113, 161, 0.6) !important;
        }


        /* 5. Styling voor TEKST-opties (wc-iro-no-image) */
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-no-image {
            min-width: unset;
            padding: 8px 12px;
            border: 2px solid #ccc !important;
            border-radius: 10px !important;
            flex-direction: row;
            align-items: center;
        }

        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-no-image .wc-iro-price {
            font-size: 0.8em;
        }

        /* 5b. Highlight voor TEKST-optie: De hele label/container krijgt de highlight */
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-no-image:hover {
            border-color: #888 !important;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2) !important;
        }

        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-no-image:has(input[type=radio]:checked) {
            border-color: #0071a1 !important;
            box-shadow: 0 0 8px rgba(0, 113, 161, 0.6) !important;
        }

        /* 5c. FIX: Reset ALLE tekst-elementen binnen de geselecteerde tekst-label. */
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-no-image:has(input[type=radio]:checked) .wc-iro-label,
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-no-image:has(input[type=radio]:checked) .wc-iro-price,
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-no-image input[type=radio]:checked~.wc-iro-label,
        .ast-woocommerce-container .wc-iro-options-wrapper .wc-iro-no-image input[type=radio]:checked~.wc-iro-price {
            color: inherit !important;
            font-weight: normal !important;
        }

        /* ================================================================= */
        /* == NIEUWE CSS VOOR HET ONDER ELKAAR PLAATSEN VAN DE VOORRAADSTATUS == */
        /* ================================================================= */

        /* 7. Nieuwe regel voor Voorraadstatus (wc-iro-label-wrap is de container) */
        .ast-woocommerce-container .wc-iro-label-wrap {
            display: flex;
            flex-direction: column;
            /* Dwingt de elementen onder elkaar */
            align-items: center;
        }

        /* Standaard opmaak voor de voorraadstatus */
        .ast-woocommerce-container .wc-iro-stock-status {
            font-size: 0.85em;
            /* Kleiner lettertype voor de status */
            font-weight: 500;
            margin-top: 2px;
            text-transform: capitalize;
            /* Zorgt voor nette weergave */
        }

        /* Optioneel: Kleuren toevoegen per status */
        .ast-woocommerce-container .wc-iro-stock-status.backorder {
            color: #ff8c00;
            /* Oranje-achtig voor Nabestelling */
        }

        .ast-woocommerce-container .wc-iro-stock-status.sold-out {
            color: #cc0000;
            /* Rood voor Uitverkocht */
        }

        .ast-woocommerce-container .wc-iro-stock-status.in-stock {
            color: #008000;
            /* Groen voor Op voorraad */
        }

        /* FIX voor de tekst-opties om label/prijs/status op 1 regel te houden */
        .ast-woocommerce-container .wc-iro-no-image .wc-iro-label-wrap {
            flex-direction: row;
            gap: 5px;
        }

        .ast-woocommerce-container .wc-iro-no-image .wc-iro-stock-status {
            margin-top: 0;
        }

        /* ================================================================= */
        /* == EINDE VAN DE VOORRAADSTATUS CSS == */
        /* ================================================================= */


        /* 6. Samenvatting en Overzicht Styling (Aangepast voor uitlijning) */
        .ast-woocommerce-container #wc-iro-summary {
            margin: 0 0 10px 0 !important;
            padding: 8px !important;
            border: 1px solid #ddd !important;
            border-radius: 6px !important;
            font-size: 14px !important;
        }

        .ast-woocommerce-container #wc-iro-summary .wc-iro-summary-list {
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .ast-woocommerce-container #wc-iro-summary .wc-iro-summary-list li {
            margin: 2px 0 !important;
        }
    </style>
    <script>
        jQuery(function ($) {
            var $priceEl = $('.summary .price').first();
            if (!$priceEl.length) $priceEl = $('.price').first();
            var $summaryList = $('#wc-iro-summary .wc-iro-summary-list');

            function parsePriceText(t) {
                if (!t) return 0;
                t = String(t).trim().replace(/[^\d\.,\-]/g, '');
                if (t === '') return 0;
                var lastDot = t.lastIndexOf('.');
                var lastComma = t.lastIndexOf(',');
                var decSep = null;
                // Logic to determine if comma or dot is the decimal separator
                if (lastDot > lastComma) decSep = '.';
                else if (lastComma > lastDot) decSep = ',';

                var parsedValue = 0;
                if (decSep) {
                    // Handle decimal separator
                    var parts = t.split(decSep);
                    var decimalPart = parts.pop().replace(/[^\d]/g, '');
                    var intPart = parts.join('').replace(/[^\d]/g, '');
                    parsedValue = parseFloat(intPart + '.' + decimalPart) || 0;
                } else {
                    // No clear separator (assume it's an integer or use the only dot/comma as decimal)
                    // For simplicity in this logic, we assume it's a whole number if no clear separation.
                    parsedValue = parseFloat(t.replace(/[^\d]/g, '')) || 0;
                }
                return parsedValue;
            }

            function formatCurrency(num) {
                // Ensure the number is formatted as a euro currency string with comma as decimal separator
                return '€' + (num.toFixed(2)).replace('.', ',');
            }

            // Store the base price if not already stored
            if (!$priceEl.data('wc-iro-base-price')) {
                $priceEl.data('wc-iro-base-price', parsePriceText($priceEl.text()));
            }

            function updateSummary() {
                var basePrice = parseFloat($priceEl.data('wc-iro-base-price')) || 0;
                var optionsPrice = 0;
                var selected = [];

                // Loop through all selected radio buttons for our option groups
                $('[name^="iro_option_"]:checked').each(function () {
                    var $opt = $(this).closest('.wc-iro-option');

                    // Skip if the option is disabled (value is -1)
                    if ($(this).val() == -1) return true;

                    var price = parseFloat($opt.attr('data-price')) || 0;
                    // Gebruik de kale label voor de samenvatting
                    var label = $opt.find('.wc-iro-label').text();

                    optionsPrice += price;
                    selected.push({ label: label, price: price });
                });

                var total = basePrice + optionsPrice;

                // Update the main product price
                var $bdi = $priceEl.find('bdi').first();
                var formattedTotal = formatCurrency(total);
                if ($bdi.length) {
                    $bdi.text(formattedTotal);
                } else {
                    $priceEl.text(formattedTotal);
                }

                // Update the summary list
                $summaryList.empty();
                $summaryList.append('<li>Basisprijs: ' + formatCurrency(basePrice) + '</li>');
                if (selected.length) {
                    selected.forEach(function (s) {
                        // Only show price adjustment if > 0
                        var priceText = s.price > 0 ? '+' + formatCurrency(s.price) : '';
                        $summaryList.append('<li>' + $('<div/>').text(s.label).html() + ': ' + priceText + '</li>');
                    });
                } else {
                    $summaryList.append('<li><em>Geen opties geselecteerd</em></li>');
                }
                $summaryList.append('<li><strong>Totaal: ' + formatCurrency(total) + '</strong></li>');
            }

            // Trigger update on option change
            $(document).on('change', '.wc-iro-option input[type=radio]', updateSummary);

            // Handle variable product price changes (e.g., when selecting a variation)
            $(document).on('found_variation', function (event, variation) {
                var newBasePrice = 0;
                if (variation && variation.display_price !== undefined) {
                    newBasePrice = parseFloat(variation.display_price) || 0;
                } else {
                    // Fallback for non-variable products or if variation data is incomplete
                    newBasePrice = parsePriceText($priceEl.text());
                }
                $priceEl.data('wc-iro-base-price', newBasePrice);
                updateSummary();
            });

            // Initial run to set prices and summary
            updateSummary();
        });
    </script>
    <?php
}
add_action('woocommerce_before_add_to_cart_button', 'wc_iro_display_image_options', 5);


// ================= Cart & Price Handling =================
function wc_iro_add_cart_item_data($cart_item_data, $product_id)
{
    $groups = wp_get_post_terms($product_id, 'iro_group_assignment');
    if (!empty($groups)) {
        $iro_selected = array();
        foreach ($groups as $group) {
            $field_name = 'iro_option_' . $group->term_id;
            if (isset($_POST[$field_name]) && intval($_POST[$field_name]) !== -1) { // Check for valid, non-disabled selection
                $options = get_term_meta($group->term_id, '_iro_options', true);
                $index = intval($_POST[$field_name]);

                // IMPORTANT: The index used here is the adjusted $activeIndex from the frontend,
                // which only counts ACTIVE (non-disabled) options. We need to find the correct
                // corresponding index in the full $options array.
                $actual_option_index = -1;
                $active_counter = 0;
                foreach ($options as $i => $option) {
                    if (empty($option['disabled'])) {
                        if ($active_counter === $index) {
                            $actual_option_index = $i;
                            break;
                        }
                        $active_counter++;
                    }
                }

                if ($actual_option_index !== -1 && isset($options[$actual_option_index])) {
                    $option_data = $options[$actual_option_index];
                    $iro_selected[] = array(
                        'group' => $group->name,
                        'label' => $option_data['label'],
                        'price' => floatval($option_data['price'])
                    );
                }
            }
        }
        if (!empty($iro_selected)) {
            $cart_item_data['iro_options'] = $iro_selected;
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'wc_iro_add_cart_item_data', 10, 2);

function wc_iro_display_cart_item_options($item_data, $cart_item)
{
    if (isset($cart_item['iro_options'])) {
        foreach ($cart_item['iro_options'] as $option) {
            $price_display = $option['price'] > 0 ? ' (+€' . number_format($option['price'], 2, ',', '.') . ')' : '';
            $item_data[] = array('name' => $option['group'], 'value' => $option['label'] . $price_display);
        }
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'wc_iro_display_cart_item_options', 10, 2);

function wc_iro_add_cart_item_price($cart_object)
{
    if (is_admin() && !defined('DOING_AJAX'))
        return;

    // Voorkom dubbele aanpassingen door te controleren of de functie al is uitgevoerd.
    // Dit is een simpele (maar niet waterdichte) manier om het probleem van de checkout te vermijden.
    // ECHTER: Beter is om de basisprijs te gebruiken (zie onder).

    foreach ($cart_object->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['iro_options'])) {
            $extra_price = 0;
            foreach ($cart_item['iro_options'] as $option)
                $extra_price += $option['price'];
            
            // 1. Haal de ORIGINELE productprijs op
            // Dit zorgt ervoor dat we altijd vanaf de basisprijs beginnen, wat het verdubbelingsprobleem oplost.
            $base_price = $cart_item['data']->get_price('edit');
            
            // Haal de product-ID op (nodig om de originele prijs te krijgen zonder eerdere hooks)
            $product_id = $cart_item['product_id'];
            $product = wc_get_product($product_id);
            
            // Gebruik de kale, ongefilterde prijs (dit is de sleutel tot succes)
            $original_price = $product->get_regular_price();
            
            // Zorg ervoor dat we de juiste prijs hebben als de variatie is geselecteerd
            if ($cart_item['variation_id']) {
                $variation = wc_get_product($cart_item['variation_id']);
                $original_price = $variation->get_regular_price();
            }
            
            // Als de reguliere prijs leeg is (bijv. bij uitverkoop), gebruik dan de verkoopprijs
            if (empty($original_price)) {
                $original_price = $base_price; 
            }
            
            // 2. Stel de nieuwe, correcte prijs in
            $new_price = $original_price + $extra_price;
            $cart_item['data']->set_price($new_price);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'wc_iro_add_cart_item_price', 20, 1);

function wc_iro_add_custom_price_data($cart_item_data, $product_id) {
    // Als je een verborgen input veld hebt gebruikt in je form:
    if (isset($_POST['wc_iro_calculated_price'])) {
        $calculated_price = floatval($_POST['wc_iro_calculated_price']);
        
        // Zorg ervoor dat de aangepaste prijs wordt opgeslagen in de sessie
        $cart_item_data['custom_price'] = $calculated_price;

        // Dit is de cruciale filter om de productprijs te overschrijven:
    }

    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'wc_iro_add_custom_price_data', 10, 2);