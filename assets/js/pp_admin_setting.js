jQuery(document).ready(function($){

	checkedFlagLoading=false;
	checkedCheckoutFlagLoading=false;	
	checkedCheckoutFlag = false;

	

	if(!$("#woocommerce_peach-payments_card_storage").is(':checked') ){
		
		//$( "#woocommerce_peach-payments_channel").parent().parent().parent().css( "background", "yellow" );
		$( "#woocommerce_peach-payments_channel").parent().parent().parent().hide();
	}

	var i = $.map($("#woocommerce_peach-payments_checkout_methods option:selected"), function(elem){
	    return $(elem).val();
	});
	
	$.each(i, function(){
		 if(this=='VISA' || this=='MASTER' || this=='AMEX' || this=='DINERS'){
		 	//alert("NITin");	    	
	    	$( "#woocommerce_peach-payments_card_storage").parent().parent().parent().parent().show();	
	    	$("#woocommerce_peach-payments_card_webhook_key").parent().parent().parent().show();
	    	//$( "#woocommerce_peach-payments_channel").parent().parent().parent().show();        	
	    	checkedFlagLoading=true;
	    	
		}

		if(this=='EFTSECURE' || this=='MOBICRED' || this=='MASTERPASS' || this=='OZOW'){		 	    	
	    	$( "#woocommerce_peach-payments_secret").parent().parent().parent().show();	    	
	    	checkedCheckoutFlagLoading=true;	    	
		}
	});


	if(!checkedFlagLoading){
		$( "#woocommerce_peach-payments_card_storage").parent().parent().parent().parent().hide();
		$("#woocommerce_peach-payments_card_webhook_key").parent().parent().parent().hide();
		//$( "#woocommerce_peach-payments_channel").parent().parent().parent().hide();
		
	}
	if(!checkedCheckoutFlagLoading){
		$( "#woocommerce_peach-payments_secret").parent().parent().parent().hide();	
	}
	//When Multi Dropdown Changed
    $("#woocommerce_peach-payments_checkout_methods").change(function(){
	    checkedFlag=false;
	    var isPPEmptyValidation        = $(this).val();
	    var i = $.map($("#woocommerce_peach-payments_checkout_methods option:selected"), function(elem){
	        return $(elem).val();
	    });
	    
	    $.each(i, function(){
	    	
	    	 if(this=='VISA' || this=='MASTER' || this=='AMEX' || this=='DINERS'){
	        	
	        	//$("#woocommerce_peach-payments_card_storage"). prop("checked", true);
	        	
	        	$( "#woocommerce_peach-payments_card_storage").parent().parent().parent().parent().show();
	        	//$( "#woocommerce_peach-payments_channel").parent().parent().parent().css( "background", "yellow" );
	        	//$( "#woocommerce_peach-payments_channel").parent().parent().parent().show();
	        	//$( "#woocommerce_peach-payments_secret").parent().parent().parent().css( "background", "red" );
	        	$( "#woocommerce_peach-payments_secret").parent().parent().parent().hide();
	        	$("#woocommerce_peach-payments_card_webhook_key").parent().parent().parent().show();
	        	checkedFlag=true;
	    	}

	    	if(this=='EFTSECURE' || this=='MOBICRED' || this=='MASTERPASS' || this=='OZOW'){
	        	
	        		        	
	        	$( "#woocommerce_peach-payments_secret").parent().parent().parent().show();
	        	//$( "#woocommerce_peach-payments_secret").parent().parent().parent().css( "background", "yellow" );
	        	
	        	checkedCheckoutFlag=true;
	    	}



	    	


	    });
	    
	    

	    if(!checkedFlag){
	    	$("#woocommerce_peach-payments_card_storage"). prop("checked", false);	    	
	    	$( "#woocommerce_peach-payments_card_storage").parent().parent().parent().parent().hide();
	    	$("#woocommerce_peach-payments_card_webhook_key").parent().parent().parent().hide();
	    	//$( "#woocommerce_peach-payments_channel").val('');
	    	//$( "#woocommerce_peach-payments_channel").parent().parent().parent().hide();


	    }

	    if(!checkedCheckoutFlag){
	    	//$( "#woocommerce_peach-payments_secret").parent().parent().parent().css( "background", "red" );
	    	   	
	    	$( "#woocommerce_peach-payments_secret").parent().parent().parent().hide();	    	


	    }
	    if (!isPPEmptyValidation){
            $( "#woocommerce_peach-payments_secret").parent().parent().parent().hide();	    	
            $( "#woocommerce_peach-payments_card_storage").parent().parent().parent().parent().hide();
        }

	   
     });
    //When Card Storage Changed 
     $("#woocommerce_peach-payments_card_storage").change(function(){
	    //$("#woocommerce_peach-payments_card_storage"). prop("checked", false);
	    
	    if($("#woocommerce_peach-payments_card_storage").is(':checked') ){
	    	$( "#woocommerce_peach-payments_channel").parent().parent().parent().show();
	    	//$( "#woocommerce_peach-payments_channel").parent().parent().parent().css( "background", "yellow" );
	    	
	    }else{
	    	$( "#woocommerce_peach-payments_channel").parent().parent().parent().hide();
	    }

	});





});
