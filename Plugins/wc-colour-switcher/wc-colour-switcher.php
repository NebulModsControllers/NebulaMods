<?php
/**
 * Plugin Name: WooCommerce Kleur Wisselaar
 * Description: Toont knoppen (gebaseerd op attribuutafbeeldingen) op de productpagina om tussen producten van dezelfde groep te wisselen.
 * Version: 1.4
 * Author: Jouw Naam
 * Requires at least: 5.0
 * Tested up to: 6.5
 * WC tested up to: 8.9
 */

if (!defined('ABSPATH')) {
    exit; // Voorkom directe toegang.
}

// -----------------------------------------------------------------
// Deel 1: Hoofdfunctie voor het tonen van de wisselaars (Front-end)
// -----------------------------------------------------------------

/**
 * Functie om de kleurschakelaar knoppen te tonen.
 */
function wc_csw_display_color_buttons()
{
    global $product;

    if (!is_product() || !$product) {
        return;
    }

    // !! BELANGRIJK: De slug van het attribuut dat de kleur definieert !!
    // Zorg ervoor dat dit overeenkomt met de slug van je attribuut (bijv. 'pa_kleur')
    $attribute_slug = 'pa_kleur';

    // 1. Haal de unieke Groeps-ID op.
    $group_id = get_post_meta($product->get_id(), 'wc-csw-group-id', true);

    if (empty($group_id)) {
        return;
    }

    // 2. Zoek alle producten die DEZELFDE Groeps-ID delen.
    $all_product_ids = wc_get_products(array(
        'status' => 'publish',
        'limit' => -1,
        'return' => 'ids',
        'meta_query' => array(
            array(
                'key' => 'wc-csw-group-id',
                'value' => $group_id,
                'compare' => '=',
            ),
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    ));

    if (empty($all_product_ids)) {
        return;
    }

    // 3. Verwerk alle gevonden producten om de knopgegevens te genereren.
    $color_options = array();
    foreach ($all_product_ids as $id) {
        $related_product = wc_get_product($id);
        if (!$related_product) {
            continue;
        }

        // Haal de attribuutterm (de kleur) op
        $terms = wp_get_post_terms($id, $attribute_slug, array('fields' => 'all'));

        if (!empty($terms) && !is_wp_error($terms)) {
            $term = $terms[0];
            $term_name = $term->name;
            $term_slug = $term->slug;

            // LOGICA: Haal de afbeelding van de attribuutterm op
            $swatch_image_url = '';
            $term_id = $term->term_id;

            // Gebruikt de 'thumbnail_id' sleutel die door de admin-functies wordt opgeslagen
            $image_id = get_term_meta($term_id, 'thumbnail_id', true);

            if ($image_id) {
                // Haal de URL op basis van de attachment ID (gebruik 'thumbnail' grootte)
                $swatch_image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
            }

            // Ga alleen verder als we een geldige swatches-afbeelding hebben
            if ($swatch_image_url) {
                // Voorkom dubbele vermeldingen
                if (!isset($color_options[$term_slug])) {
                    $color_options[$term_slug] = array(
                        'name' => $term_name,
                        'url' => $related_product->get_permalink(),
                        'is_current' => ($id == $product->get_id()),
                        'image_url' => $swatch_image_url,
                    );
                }
            }
        }
    }

    if (empty($color_options)) {
        return;
    }

    // 4. Toon de knoppen HTML.
    echo '<div class="wc-color-switcher-wrapper">';
    echo '<p class="wc-csw-title">Kleuren:</p>';
    echo '<div class="wc-csw-buttons">';

    foreach ($color_options as $option) {
        $class = 'wc-csw-button';
        if ($option['is_current']) {
            $class .= ' is-current';
        }

        printf(
            '<a href="%s" class="%s" title="%s"><img src="%s" alt="%s" /></a>',
            esc_url($option['url']),
            esc_attr($class),
            esc_attr($option['name']),
            esc_url($option['image_url']),
            esc_attr($option['name'])
        );
    }

    echo '</div>'; // .wc-csw-buttons
    echo '</div>'; // .wc-color-switcher-wrapper
}

// Haak de functie in BOVEN de productopties
add_action('woocommerce_before_add_to_cart_form', 'wc_csw_display_color_buttons', 1);


// -----------------------------------------------------------------
// Deel 2: CSS voor de kleurschakelaar (Styling)
// -----------------------------------------------------------------

/**
 * Basis CSS voor de kleurschakelaar.
 */
/**
 * Basis CSS voor de kleurschakelaar, INCLUSIEF tooltip-effect.
 */
function wc_csw_styles()
{
    if (!is_product()) {
        return;
    }
    ?>
    <style>
        .wc-color-switcher-wrapper {
            margin-bottom: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .wc-csw-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .wc-csw-buttons {
            display: flex;
            width: 380px;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Basis styling voor de knoppen */
        .wc-csw-button {
            display: inline-block;
            border: 2px solid #ccc;
            border-radius: 4px;
            text-decoration: none;
            line-height: 0;
            transition: all 0.2s ease;
            padding: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            
            /* Cruciaal: Stelt de positiecontext in voor de tooltip */
            position: relative; 
        }

        .wc-csw-button img {
            display: block;
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 2px;
        }

        /* Hover effecten */
        .wc-csw-button:hover {
            border-color: #888;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .wc-csw-button.is-current {
            border-color: #0071a1;
            box-shadow: 0 0 0 2px #e0f0f5;
            cursor: default;
            pointer-events: none;
        }

        /* ======================================================= */
        /* CUSTOM TOOLTIP STYLING */
        /* ======================================================= */

        /* De Tooltip Box zelf (gemaakt met :after) */
        .wc-csw-button:after {
            content: attr(title); 
            
            /* Positie & uiterlijk */
            position: absolute;
            bottom: 110%;
            left: 50%;
            transform: translateX(-50%);
            
            background-color: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 5px 8px;
            border-radius: 3px;
            white-space: nowrap;
            font-size: 12px;
            line-height: 1.2;
            
            /* >> WIJZIGING: Hoge Z-INDEX << */
            z-index: 2147483645; 
            
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.1s ease, visibility 0.1s ease;
            pointer-events: none;
        }

        /* De Driehoek (pijltje) onder de Tooltip (gemaakt met :before) */
        .wc-csw-button:before {
            content: "";
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            
            /* Maakt een klein driehoekje */
            border: 5px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.85);
            
            /* >> WIJZIGING: Hoge Z-INDEX << */
            z-index: 2147483646; /* Nog hoger dan de box zelf */
            
            /* Standaard onzichtbaar */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.1s ease, visibility 0.1s ease;
            pointer-events: none;
        }

        /* Toon de Tooltip en de Driehoek bij HOVER */
        .wc-csw-button:hover:after,
        .wc-csw-button:hover:before {
            opacity: 1;
            visibility: visible;
        }
    </style>
    <?php
}
add_action('wp_head', 'wc_csw_styles');


// -----------------------------------------------------------------
// Deel 3: Custom Functies voor de Admin (Beheer)
// -----------------------------------------------------------------

// !! De volgende functies voegen het uploadveld toe aan de attribuuttermen
// !! Dit gebruikt 'pa_kleur'. Pas dit aan als je attribuut een andere slug heeft.

/**
 * 3.1: Voegt het afbeeldingsselectieveld toe aan het formulier voor nieuwe productattribuut-termen.
 */
function wc_csw_add_term_image_field()
{
    if (!isset($_GET['taxonomy']) || 'pa_kleur' !== $_GET['taxonomy']) {
        return;
    }
    ?>
    <div class="form-field term-thumbnail-wrap">
        <label for="color_swatch_thumbnail_id"><?php esc_html_e('Kleur Swatch Afbeelding', 'woocommerce'); ?></label>
        <div id="color_swatch_thumbnail" style="float: left; margin-right: 10px;">
            <img src="<?php echo esc_url(wc_placeholder_img_src()); ?>" width="60px" height="60px" />
        </div>
        <div style="line-height: 60px;">
            <input type="hidden" id="color_swatch_thumbnail_id" name="color_swatch_thumbnail_id" />
            <button type="button"
                class="upload_image_button button"><?php esc_html_e('Afbeelding uploaden/selecteren', 'woocommerce'); ?></button>
            <button type="button"
                class="remove_image_button button hidden"><?php esc_html_e('Afbeelding verwijderen', 'woocommerce'); ?></button>
        </div>
        <div class="clear"></div>
        <p class="description">
            <?php esc_html_e('Kies een kleine afbeelding die dit kleur/patroon representeert.', 'woocommerce'); ?></p>
    </div>
    <?php
}
add_action('pa_kleur_add_form_fields', 'wc_csw_add_term_image_field');

/**
 * 3.2: Voegt het afbeeldingsselectieveld toe aan het formulier voor het bewerken van bestaande productattribuut-termen.
 */
function wc_csw_edit_term_image_field($term)
{
    if ('pa_kleur' !== $term->taxonomy) {
        return;
    }

    $image_id = absint(get_term_meta($term->term_id, 'thumbnail_id', true));

    if ($image_id) {
        $image_url = wp_get_attachment_thumb_url($image_id);
    } else {
        $image_url = wc_placeholder_img_src();
    }
    ?>
    <tr class="form-field term-thumbnail-wrap">
        <th scope="row" valign="top"><label><?php esc_html_e('Kleur Swatch Afbeelding', 'woocommerce'); ?></label></th>
        <td>
            <div id="color_swatch_thumbnail" style="float: left; margin-right: 10px;">
                <img src="<?php echo esc_url($image_url); ?>" width="60px" height="60px" />
            </div>
            <div style="line-height: 60px;">
                <input type="hidden" id="color_swatch_thumbnail_id" name="color_swatch_thumbnail_id"
                    value="<?php echo esc_attr($image_id); ?>" />
                <button type="button"
                    class="upload_image_button button"><?php esc_html_e('Afbeelding uploaden/selecteren', 'woocommerce'); ?></button>
                <button type="button"
                    class="remove_image_button button <?php echo $image_id ? '' : 'hidden'; ?>"><?php esc_html_e('Afbeelding verwijderen', 'woocommerce'); ?></button>
            </div>
            <div class="clear"></div>
            <p class="description">
                <?php esc_html_e('Kies een kleine afbeelding die dit kleur/patroon representeert.', 'woocommerce'); ?></p>
        </td>
    </tr>
    <?php
}
add_action('pa_kleur_edit_form_fields', 'wc_csw_edit_term_image_field', 10, 2);

/**
 * 3.3: Slaat de attribuutafbeelding ID op wanneer een term wordt opgeslagen/bijgewerkt.
 */
function wc_csw_save_term_image_field($term_id)
{
    if (!isset($_POST['taxonomy']) || 'pa_kleur' !== $_POST['taxonomy']) {
        return;
    }

    $image_id = isset($_POST['color_swatch_thumbnail_id']) ? absint($_POST['color_swatch_thumbnail_id']) : 0;

    // Sla de ID op onder de sleutel die de hoofdfunctie verwacht: 'thumbnail_id'
    if ($image_id) {
        update_term_meta($term_id, 'thumbnail_id', $image_id);
    } else {
        delete_term_meta($term_id, 'thumbnail_id');
    }
}
add_action('edited_pa_kleur', 'wc_csw_save_term_image_field');
add_action('create_pa_kleur', 'wc_csw_save_term_image_field');

/**
 * 3.4: Voegt de JavaScript toe om de Media Uploader in de admin te gebruiken.
 */
function wc_csw_media_uploader_scripts()
{

    // Check of we op de juiste admin-pagina zijn:
    if (!empty($_GET['taxonomy']) && $_GET['taxonomy'] === 'pa_kleur') {

        // Laad de benodigde WordPress media scripts en stijlen
        wp_register_script('wc-csw-term-media', '');
        wp_enqueue_script('wc-csw-term-media');

        // Registreer en Enqueue jouw custom JavaScript code
        wp_enqueue_script(
            'wc-csw-term-media', // Unieke handle
            false,               // Geen extern bestand
            array('jquery', 'media-editor'), // Afhankelijkheden
            '1.0',               // Versie
            true                 // In de footer laden
        );

        // De daadwerkelijke JavaScript-code toevoegen als inline script
        $script = "
            jQuery(document).ready(function($){
                var frame;

                // Upload knop klik
                $('.upload_image_button').on( 'click', function( event ) {
                    event.preventDefault();
                    var button = $(this);
                    var img_wrap = button.closest('.form-field, .form-row').find('#color_swatch_thumbnail');
                    var img_id_field = button.siblings('#color_swatch_thumbnail_id');
                    var remove_btn = button.siblings('.remove_image_button');

                    // Maak of hergebruik de frame
                    if ( frame ) {
                        frame.open();
                        return;
                    }

                    // Configureer de WordPress Media Uploader
                    frame = wp.media({
                        title: '" . esc_html__('Kies een Kleur Swatch Afbeelding', 'woocommerce') . "',
                        button: {
                            text: '" . esc_html__('Gebruik deze afbeelding', 'woocommerce') . "'
                        },
                        multiple: false
                    });

                    // Wanneer een afbeelding is geselecteerd
                    frame.on( 'select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        
                        img_wrap.find('img').attr('src', attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                        img_id_field.val( attachment.id );
                        remove_btn.removeClass('hidden');
                    });

                    frame.open();
                });

                // Verwijder knop klik
                $('.remove_image_button').on( 'click', function( event ) {
                    event.preventDefault();
                    var button = $(this);
                    var img_wrap = button.closest('.form-field, .form-row').find('#color_swatch_thumbnail');
                    var img_id_field = button.siblings('#color_swatch_thumbnail_id');
                    
                    img_wrap.find('img').attr('src', '" . esc_url(wc_placeholder_img_src()) . "');
                    img_id_field.val('');
                    button.addClass('hidden');
                });
            });
        ";

        wp_add_inline_script('wc-csw-term-media', $script);
    }
}
add_action('admin_enqueue_scripts', 'wc_csw_media_uploader_scripts');