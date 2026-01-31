jQuery(document).ready(function($) {
    // Luister naar veranderingen in de radio buttons
    $('.wc-iro-options-wrapper input[type="radio"]').on('change', function() {
        var $wrapper = $(this).closest('.wc-iro-options-wrapper');
        
        // Reset alle tekstopties
        $wrapper.find('.wc-iro-no-image .wc-iro-label, .wc-iro-no-image .wc-iro-price').each(function() {
            // Verwijder expliciet alle inline styles (die door een ander script zijn toegevoegd)
            $(this).css({
                'color': '', // Reset de inline kleur
                'font-weight': '' // Reset het inline lettertype-gewicht
            });
        });

        // Alleen de geselecteerde tekstoptie heeft de border-highlight (via CSS)
        
        // Optioneel: Forceer nogmaals de reset voor de geselecteerde optie 
        // (dit is de agressieve stap)
        if ($(this).closest('.wc-iro-no-image').length) {
             $(this).siblings('.wc-iro-label, .wc-iro-price').css({
                'color': 'inherit', // Zet kleur terug naar de CSS 'inherit'
                'font-weight': 'normal' // Zet gewicht terug naar de CSS 'normal'
            });
        }
    });
});

jQuery(function($) {
    // Functie om de prijs van alle geselecteerde opties te berekenen
    function calculateTotalPriceAdjustment() {
        var totalAdjustment = 0;
        
        // Loop door elke optie-groep
        $('.wc-iro-group').each(function() {
            var $selectedOption = $(this).find('input[type="radio"]:checked');
            
            if ($selectedOption.length) {
                // Vind het parent <label> element dat de data-price bevat
                var $label = $selectedOption.closest('.wc-iro-option');
                var price = parseFloat($label.data('price') || 0);
                
                // Voeg de prijs van de geselecteerde optie toe
                totalAdjustment += price;
            }
        });
        
        return totalAdjustment;
    }

    // Functie die de prijs van het hoofdproduct op de pagina bijwerkt
    function updateProductPriceDisplay(adjustment) {
        // Gebruik de standaard WooCommerce hook om de prijs te updaten
        // (Dit is vaak de prijs onder de producttitel)
        $('form.cart').trigger('wc_iro_update_price', [adjustment]);
        
        // Optioneel: Update de samenvatting direct
        // U kunt hier ook uw #wc-iro-summary bijwerken.
    }
    
    // Functie om de totale cart te verversen via AJAX (Cruciaal voor mini-cart)
    function refreshCartFragments() {
        // Trigger de WooCommerce actie om de mini-cart via AJAX te verversen
        // Dit zorgt ervoor dat de prijs in de slide-out/dropdown wordt bijgewerkt.
        $('body').trigger('wc_fragment_refresh');
    }

    // === Event Handlers ===
    
    // 1. Wanneer een optie wordt gewijzigd
    $(document).on('change', '.wc-iro-options-wrapper input[type="radio"]', function() {
        var newAdjustment = calculateTotalPriceAdjustment();
        updateProductPriceDisplay(newAdjustment);
    });

    // 2. Wanneer het product aan de winkelwagen wordt toegevoegd
    $(document).on('submit', 'form.cart', function(e) {
        var $form = $(this);
        var totalAdjustment = calculateTotalPriceAdjustment();
        
        // Cruciaal: Voeg een verborgen veld toe met de totale aanpassing
        // Dit veld moet in de add-to-cart POST request worden meegestuurd.
        // De waarde wordt door uw toekomstige PHP-code verwerkt.
        $form.find('input[name="wc_iro_price_adjustment"]').remove();
        $form.append('<input type="hidden" name="wc_iro_price_adjustment" value="' + totalAdjustment.toFixed(2) + '">');
        
        // Zorg ervoor dat de cart na de succesvolle toevoeging wordt ververst
        // Dit is meestal al de standaard actie van WooCommerce na een succesvolle AJAX add-to-cart.
        // U kunt refreshCartFragments() hier optioneel toevoegen voor de zekerheid.
    });

    // Handle Astra minicart fragment refresh (from wcIroSettings)
    if (typeof wcIroSettings !== 'undefined' && wcIroSettings.minicart_selectors) {
        var selectors = wcIroSettings.minicart_selectors.join(', ');
        $(document).on('click', selectors, function() {
            $('body').trigger('wc_fragment_refresh');
        });
    }
    
    // Initialiseer bij laden
    var initialAdjustment = calculateTotalPriceAdjustment();
    updateProductPriceDisplay(initialAdjustment);
});