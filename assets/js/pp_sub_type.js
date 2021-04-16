  gtag('js', new Date());
  gtag('event', 'invoking the Subscription process', {
    'event_category': merchant.siteurl,
    'event_label': merchant.transaction_id,
    'value': '1',    
    'send_to' : 'peach'
    });