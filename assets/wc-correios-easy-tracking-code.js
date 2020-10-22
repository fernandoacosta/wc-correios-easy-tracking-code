jQuery( document ).ready( function( $ ) {

  $( '.wc-correios-tracking' ).on( 'click', function( event ) {
    event.preventDefault();

    $( this ).parent().find( '.wc-correios-tracking-field' ).slideToggle();
    $( this ).parent().find( '.wc-correios-tracking-field' ).focus();
  });

  // ignore click
  $( '.wc-correios-tracking-field' ).on( 'click', function( event ) {
    event.preventDefault();
  });

  $( '.wc-correios-tracking-field' ).on( 'keypress', function( event ) {

    var order_id = $( this ).data( 'order-id' );
        field    = $( this );

    if( 13 == event.which ) { //enter
      event.preventDefault();

      $.ajax({
        type:     'POST',
        url:      woocommerce_admin_meta_boxes.ajax_url,
        cache:    false,
        data:     {
          action:       'wc_add_correios_tracking',
          tracking:     $( this ).val(),
          order_id:     order_id,
        },

        beforeSend: function () {
          field.removeClass( 'success-save' );
          field.removeClass( 'error-save' );
        },
        success: function ( data ) {

          if ( data.success ) {
            field.addClass( 'success-save' );
            field.removeClass( 'error-save' );
          } else {
            field.removeClass( 'success-save' );
            field.addClass( 'error-save' );
          }

        },
        error: function () {
          alert( 'Ocorreu um erro ao processar. Por favor, tente novamente.' );
        }
      });
    }
  });
});
