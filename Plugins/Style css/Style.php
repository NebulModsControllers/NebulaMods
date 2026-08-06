<?php
/**
 * Plugin Name: Mijn WooCommerce In-line Stijlen
 * Plugin URI:  https://example.com/
 * Description: Laadt de aangepaste CSS-stijlen voor WooCommerce direct in de header.
 * Version:     1.0.1 
 * Author:      Gerben van Houtum
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Zorg ervoor dat het bestand niet direct kan worden benaderd
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Voegt de CSS direct toe aan de <head> van de website.
 * Dit zorgt ervoor dat de stijlen zeer vroeg en met hoge prioriteit worden geladen.
 */
function mijn_wc_inline_stijlen() {
    // Definieer de CSS-code
    $custom_css = "
        /* =================================================================
           DEEL 2: Productkaarten Omhoog Halen (Alleen opschonen)
           ================================================================= */
        
        /* HEADER: Reserve space and avoid CLS when menu loads (prevents vertical/double menus)
           - Reserves header height so late-inserted menu won't push content.
           - Keeps nav horizontal before JS runs and hides cloned menus by default.
        */
        .site-header, .masthead, header#masthead {
             min-height: 72px;
             height: 72px;
             box-sizing: border-box;
        }
        .site-header .main-navigation, .main-navigation {
             display: flex !important;
             align-items: center;
             justify-content: space-between;
             flex-wrap: nowrap;
        }
        /* Keep the menu invisible until JS marks the page ready to avoid FOUC/CLS */
        .main-navigation .menu, .site-header .menu {
             transition: opacity 0.18s ease;
             opacity: 0;
             pointer-events: none;
        }
        .js-menu-ready .main-navigation .menu, .js-menu-ready .site-header .menu {
             opacity: 1;
             pointer-events: auto;
        }
        /* Hide commonly used clone classes if a plugin/theme creates a duplicate menu */
        .main-navigation .cloned, .main-navigation .clone, .main-navigation .menu-clone {
             display: none !important;
        }

        /* Minimaliseert de marge van de container boven de productlijst */
        .woocommerce .woocommerce-notices,
        .woocommerce ul.products {
             margin-top: 10px !important;
        }
        
        
        /* PRODUCT CARD BASE STYLE */
        .woocommerce ul.products li.product {
             background: #ffffff;
             border-radius: 12px;
             padding: 12px;
             box-shadow: 0 12px 28px rgba(0, 128, 128, 0.08);
             border: 1px solid rgba(0, 128, 128, 0.1);
             transform: translateZ(0);
             transition: transform 0.15s ease, box-shadow 0.12s ease;
             will-change: box-shadow, transform;
        }
        
        
        /* PRODUCT IMAGE FLOAT + GLOW */
        .woocommerce ul.products li.product img {
             transition: transform 0.3s ease, filter 0.3s ease;
             will-change: transform, filter;
        }
        .woocommerce ul.products li.product:hover img {
             transform: translateY(-3px);
             filter: drop-shadow(0 0 10px rgba(0, 128, 128, 0.7));
        }
        
        @keyframes softPulse {
             0%, 100% { box-shadow: 0 0 18px 4px rgba(0, 128, 128, 0.22); }
             50% { box-shadow: 0 0 26px 6px rgba(0, 128, 128, 0.30); }
        }
        
        .woocommerce ul.products li.product:hover {
             transform: translateY(-4px);
             animation: softPulse 2s infinite ease-in-out;
             box-shadow: 0 0 22px 5px rgba(0, 128, 128, 0.38), 0 16px 32px rgba(0, 128, 128, 0.18);
             border-color: rgba(0, 128, 128, 0.25);
        }
        
        
        /* Hides the product description from the order summary on the block checkout page */
        .wc-block-components-product-metadata__description {
             display: none !important;
        }
        
        /* Verbergt alle breadcrumbs in de WooCommerce-omgeving (inclusief catalogus/categorieën) */
        .woocommerce-breadcrumb {
             display: none; 
        }
        

        
        
        /* Minimaliseert de marge ONDER de Astra Voorraad/Beschikbaarheid detail. */
        .ast-stock-detail {
             /* De kern van de oplossing: vermindert de ruimte onder het element */
             margin-bottom: 5px !important; 
        }
        
        /* Fix voor de container direct boven de knop, voor de zekerheid */
        .woocommerce-variation-add-to-cart {
             margin-top: 5px !important; 
        }
        
        
        /* Nette uitlijning van productopties met lichte inspringing */
        .woocommerce ul.cart_list li .variation,
        .woocommerce-mini-cart .variation,
        .woocommerce ul.product_list_widget li .variation {
             margin: 0;
             padding-left: 1.5em; /* <-- Dit bepaalt de 'tab' naar rechts */
        }
        
        /* Zorg dat elk label en waarde op een nieuwe regel staan */
        .woocommerce ul.cart_list li .variation dt,
        .woocommerce-mini-cart .variation dt,
        .woocommerce ul.product_list_widget li .variation dt {
             float: left;
             clear: left;
             margin-right: 0.4em;
        }
        
        .woocommerce ul.cart_list li .variation dd,
        .woocommerce-mini-cart .variation dd,
        .woocommerce ul.product_list_widget li .variation dd {
             display: block;
             margin: 0;
        }
        
        

        
        
        /* 1. Verminder de verticale afstand tussen de productrijen.
             Focus op de individuele LI elementen, die de afstand naar de volgende rij bepalen. */
        .woocommerce ul.products li.product {
             /* Verlaag de marge onder de kaart (dit is vaak de boosdoener voor verticale afstand) */
             margin-bottom: 15px !important; /* Standaard is dit vaak 30px of 40px. */
              
             /* Verlaag de padding onder de kaart (indien gebruikt door Astra/WooCommerce) */
             padding-bottom: 5px !important; 
              
             /* Houd de horizontale padding (ruimte tussen kolommen) op de gewenste 10px */
             padding-left: 10px !important; 
             padding-right: 10px !important;
        }
        
        /* 2. Zorg voor een minimale marge boven de productlijst zelf, zodat alles compact blijft. */
        ul.products {
             margin-top: 10px !important;
        }
        
        /* 3. Optioneel: Als je een border/schaduw hebt en de padding niet werkt, probeer dit: */
        .woocommerce ul.products li.product .astra-woo-product-info {
             padding-bottom: 5px !important;
        }
        
        
     /* Media Query: Schakelt de absolute positionering uit op schermen kleiner dan 1200px */
     @media (max-width: 1200px) {
              
             /* 1. Reset de sorteer-dropdown naar de normale flow */
             .woocommerce-ordering {
                 position: static !important; /* Terug naar normale flow */
                 margin-top: 15px !important; /* Voegt wat ruimte boven het element toe */
                 margin-right: auto !important; /* Centreert het element op de pagina */
                 margin-left: auto !important;
                 width: 100%; /* Zorgt ervoor dat hij de hele breedte inneemt */
                  
                 /* De dropdown is nu weer een normaal blok-element. */
             }
        
             /* 2. Reset de hoofdcontent container (waar de absolute positie vandaan kwam) */
             .site-content .ast-container {
                 position: static !important;
             }
              
             /* 3. Zorg dat de titel en de dropdown niet botsen als ze op verschillende regels staan */
             .woocommerce-products-header .page-title {
                 margin-top: 0 !important; /* Reset marges voor de kleinere schermen */
                 margin-bottom: 20px !important;
                 text-align: center; /* Optioneel: centreer de titel op mobiel */
             }
        }
        
        /* =================================================================
           DEEL 1: Positioneer de Dropdown Boven de Titel
           ================================================================= */
        
        /* 1. Maak de hoofdcontent container de referentie voor de absolute positie */
        .site-content .ast-container {
             position: relative; 
             padding-top: 10px !important; /* Minimaliseert de padding bovenaan (schuift titel op) */
        }
        
        /* 2. Positioneer de sorteer-dropdown absoluut. */
        .woocommerce-ordering {
             position: absolute; 
             top: 30px;           /* AANGEPAST! Verlaag deze waarde om de dropdown zichtbaar te maken. */
             right: 0;           /* Plaatst de knop aan de uiterste rechterkant. */
             z-index: 10;        /* Zorgt ervoor dat deze boven andere elementen zweeft. */
             margin: 0 !important; /* Verwijdert alle marges */
        }
        
        /* 3. Verberg en reset de onnodige elementen */
        .woocommerce-result-count {
             display: none; 
        }
        
        .woocommerce-products-header .page-title {
             /* Zorg dat de titel mooi begint en niet te veel ruimte inneemt */
             margin-top: 20px !important; /* Creëert ruimte onder de dropdown */
             margin-bottom: 10px !important; 
             padding: 0 !important;
        }
    ";
    
     // Voeg de CSS toe met een inline block
     echo '<style type="text/css">' . $custom_css . '</style>';
     // Mark the document when JS is ready so the menu can fade in without causing CLS
     echo '<script>document.addEventListener("DOMContentLoaded", function(){document.documentElement.classList.add("js-menu-ready");});</script>';
}

// Haak de functie aan de 'wp_head' actie om de CSS in de <head> te plaatsen.
add_action( 'wp_head', 'mijn_wc_inline_stijlen' );