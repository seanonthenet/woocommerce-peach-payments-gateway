  gtag('js', new Date());
  gtag('event', merchant.event_type, {
    'event_category': 'payment_finished',
    'event_label': merchant.payment_method,
     'value': '',   
    'send_to' : 'peach'
    });
 