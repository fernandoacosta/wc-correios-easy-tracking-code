/**
 * Coluna "Rastreio" da lista de pedidos: adicionar (botão ou Enter) e tirar códigos dos Correios sem abrir o pedido.
 * Sem jQuery. A célula tem `no-link`, para o clique não abrir o pedido (regra da lista do WooCommerce).
 */
( function () {
  var config = window.wcCorreiosEasyTracking;

  if ( ! config ) {
    return;
  }

  var texts = config.texts || {};

  function setStatus( cell, text, state ) {
    cell.querySelector( '.wcet__status' ).textContent = text || '';
    cell.classList.remove( 'is-saving', 'is-saved', 'is-error' );
    if ( state ) {
      cell.classList.add( 'is-' + state );
    }
  }

  function send( cell, code, remove ) {
    var body = new URLSearchParams();
    body.append( 'action', 'wc_add_correios_tracking' );
    body.append( 'security', config.nonce );
    body.append( 'order_id', cell.dataset.orderId );
    body.append( 'tracking', code );
    if ( remove ) {
      body.append( 'remove', '1' );
    }

    cell.setAttribute( 'aria-busy', 'true' );
    setStatus( cell, texts.saving, 'saving' );

    return fetch( config.ajax_url, { method: 'POST', credentials: 'same-origin', body: body } )
      .then( function ( response ) {
        return response.json().catch( function () {
          return {};
        } );
      } )
      .then( function ( json ) {
        if ( ! json || ! json.success ) {
          throw new Error( ( json && json.data && json.data.message ) || texts.error );
        }
        cell.querySelector( '.wcet__codes' ).innerHTML = json.data.html;
        setStatus( cell, remove ? texts.removed : texts.saved, 'saved' );
        return true;
      } )
      .catch( function ( error ) {
        setStatus( cell, error.message || texts.error, 'error' );
        return false;
      } )
      .finally( function () {
        cell.removeAttribute( 'aria-busy' );
      } );
  }

  function add( cell ) {
    var input = cell.querySelector( '.wcet__input' );
    var code = input.value.replace( /\s+/g, '' ).toUpperCase();

    if ( '' === code ) {
      setStatus( cell, texts.empty, 'error' );
      input.focus();
      return;
    }

    if ( 'true' === cell.getAttribute( 'aria-busy' ) ) {
      return;
    }

    send( cell, code, false ).then( function ( ok ) {
      if ( ok ) {
        input.value = '';
      }
      input.focus();
    } );
  }

  document.addEventListener( 'click', function ( event ) {
    var target = event.target instanceof Element ? event.target : null;
    var cell = target && target.closest( '.wcet' );

    if ( ! cell ) {
      return;
    }

    if ( target.closest( '.wcet__save' ) ) {
      event.preventDefault();
      add( cell );
      return;
    }

    var remove = target.closest( '.wcet__remove' );
    if ( remove ) {
      event.preventDefault();
      if ( 'true' !== cell.getAttribute( 'aria-busy' ) && window.confirm( texts.confirm ) ) {
        send( cell, remove.dataset.code, true ).then( function () {
          cell.querySelector( '.wcet__input' ).focus();
        } );
      }
    }
  } );

  // Enter salva; nunca envia o formulário de filtros da lista, que envolve a tabela.
  document.addEventListener( 'keydown', function ( event ) {
    var target = event.target instanceof Element ? event.target : null;
    if ( 'Enter' !== event.key || ! target || ! target.classList.contains( 'wcet__input' ) ) {
      return;
    }
    event.preventDefault();
    add( target.closest( '.wcet' ) );
  } );

  // Ao digitar de novo, o aviso de erro sai.
  document.addEventListener( 'input', function ( event ) {
    var target = event.target instanceof Element ? event.target : null;
    if ( target && target.classList.contains( 'wcet__input' ) ) {
      target.closest( '.wcet' ).classList.remove( 'is-error' );
    }
  } );
} )();
