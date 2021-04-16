jQuery(function(){
	jQuery("body").block(
		{
			message: "Thank you for your order. We are now redirecting you to Checkout to make payment.",
			overlayCSS:
			{
				background: "#fff",
				opacity: 0.6
			},
			css: {
				padding:        20,
				textAlign:      "center",
				color:          "#555",
				border:         "3px solid #aaa",
				backgroundColor:"#fff",
				cursor:         "wait"
			}
		});
	jQuery( "#submit_checkout_payment_form" ).click();


	/*$('#allot').click(function () {
	});*/

});
							