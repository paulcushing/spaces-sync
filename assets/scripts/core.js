
const spacessync_loader = jQuery('.spacessync__loader')
const spacessync_message = jQuery('.spacessync__message')
const spacessync_test_connection = jQuery('.spacessync__test__connection')

jQuery( function () {
  spacessync_test_connection.on( 'click', function () {

    console.log( 'Testing DigitalOcean Connection' )

    const data = {
      spacessync_key: jQuery('input[name=spacessync_key]').val(),
      spacessync_secret: jQuery('input[name=spacessync_secret]').val(),
      spacessync_endpoint: jQuery('input[name=spacessync_endpoint]').val(),
      spacessync_container: jQuery('input[name=spacessync_container]').val(),
      action: 'spacessync_test_connection'
    }

    spacessync_loader.hide()

    jQuery.ajax({
      type: 'POST',
      url: ajaxurl,
      data: data,
      dataType: 'html'
    }).done( function ( res ) {
      spacessync_message.show()
      spacessync_message.html('<br/>' + res)
      spacessync_loader.hide()
      jQuery('html,body').animate({ scrollTop: 0 }, 1000)
    })

  })

})