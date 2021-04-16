    gtag('js', new Date());
  gtag('event', 'proceed_to_payment', {
    'event_category': 'checkout',
    'event_label': merchant.basket,
    'value': '',     
    'send_to' : 'peach'
    });
 /*  gtag('event', merchant.checkoutPaymentMethod, {
    'event_category': 'checkout',
    'event_label': merchant.basket,
    'value': '1',    
    'send_to' : 'peach'
    });
  */