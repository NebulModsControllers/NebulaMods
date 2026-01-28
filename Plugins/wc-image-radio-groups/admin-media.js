jQuery(document).ready(function($) {

    // Functie om de media-uploader te initialiseren en de context direct te koppelen.
    function initializeMediaUploader($button, $mediaInput, $previewWrap, $previewImage) {
        
        // Maak de uploader aan (deze is nu lokaal)
        var local_uploader = wp.media.frames.file_frame = wp.media({
            title: 'Kies Afbeelding voor Optie Override',
            button: {
                text: 'Gebruik deze afbeelding'
            },
            multiple: false 
        });

        // 1. Koppel de HUIDIGE context direct aan het JQUERY-OBJECT van de uploader.
        // Dit is betrouwbaarder dan closures of .options.data
        local_uploader.$el.data('context', {
            $mediaInput: $mediaInput,
            $previewWrap: $previewWrap,
            $previewImage: $previewImage,
            $selectButton: $button
        });

        // 2. Definieer de 'select' handler.
        local_uploader.on('select', function() {
            var attachment = local_uploader.state().get('selection').first().toJSON();
            
            // 3. HAAL DE CONTEXT OP van het uploader-object zelf.
            var context = local_uploader.$el.data('context'); 
            
            if (context) {
                // Gebruik de LOKAAL opgeslagen velden
                context.$mediaInput.val(attachment.id);
                context.$previewImage.attr('src', attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                
                context.$previewWrap.show();
                context.$selectButton.hide();
            }
        });

        return local_uploader;
    }

    // Wanneer er op 'Selecteer Afbeelding' wordt geklikt
    $(document).on('click', '.wc-iro-select-image-button', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        
        var $mediaInput = $button.siblings('.wc-iro-media-id-field');
        var $previewWrap = $button.siblings('.wc-iro-media-preview-wrap');
        var $previewImage = $previewWrap.find('.wc-iro-media-preview');

        // Initialiseer de uploader en koppel de context
        var uploader = initializeMediaUploader($button, $mediaInput, $previewWrap, $previewImage);
        
        uploader.open();
    });

    // De "Verwijder Afbeelding" handler (blijft hetzelfde)
    $(document).on('click', '.wc-iro-remove-image-button', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $previewWrap = $button.closest('.wc-iro-media-preview-wrap');
        
        var $mediaInput = $previewWrap.siblings('.wc-iro-media-id-field');
        var $selectButton = $previewWrap.siblings('.wc-iro-select-image-button');

        $mediaInput.val('');
        
        $previewWrap.hide();
        $selectButton.show();
    });
});