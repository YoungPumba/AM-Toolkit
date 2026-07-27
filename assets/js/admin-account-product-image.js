(() => {
    'use strict';

    jQuery(($) => {
        const field = $('.amt-account-product-image');

        if (!field.length || !window.wp?.media) {
            return;
        }

        const input = field.find('#amt-account-product-image-id');
        const preview = field.find('.amt-account-product-image__preview');
        const removeButton = field.find('.amt-account-product-image__remove');
        const placeholder = field.data('placeholder');
        let mediaFrame = null;

        field.on('click', '.amt-account-product-image__select', (event) => {
            event.preventDefault();

            const button = $(event.currentTarget);

            if (!mediaFrame) {
                mediaFrame = window.wp.media({
                    title: button.data('frame-title'),
                    button: {
                        text: button.data('frame-button')
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: false
                });

                mediaFrame.on('select', () => {
                    const attachment = mediaFrame.state().get('selection').first()?.toJSON();

                    if (!attachment?.id || !attachment?.url) {
                        return;
                    }

                    const previewUrl =
                        attachment.sizes?.medium?.url ||
                        attachment.sizes?.thumbnail?.url ||
                        attachment.url;

                    input.val(attachment.id);
                    preview.html(
                        $('<img>', {
                            class: 'amt-account-product-image__preview-image',
                            src: previewUrl,
                            alt: ''
                        })
                    );
                    removeButton.prop('hidden', false);
                });
            }

            mediaFrame.open();
        });

        field.on('click', '.amt-account-product-image__remove', (event) => {
            event.preventDefault();

            input.val('');
            preview.html(
                $('<span>', {
                    class: 'amt-account-product-image__placeholder',
                    text: placeholder
                })
            );
            removeButton.prop('hidden', true);
        });
    });
})();
