  //alert(merchant.payment_method);
  gtag('js', new Date());
  gtag('event', 'gateway_selection', {
    'event_category': 'checkout',
    'event_label': merchant.basket,
    'value': '',    
    'send_to' : 'peach'
    });

  gtag('event', merchant.payment_method, {
    'event_category': 'checkout',
    'event_label': merchant.basket,
    'value': '',    
    'send_to' : 'peach'
    });
 
gtag('set', 'dimension1', merchant.siteurl);
gtag('set', 'dimension2', 'WooCommerce');
gtag('set', 'dimension3', merchant.transaction_id);
gtag('set', 'dimension4', merchant.pp_version);
gtag('set', 'dimension5', merchant.wc_version);
gtag('set', 'dimension6', merchant.wp_version);
gtag('set', 'dimension7', merchant.pp_mode);

 