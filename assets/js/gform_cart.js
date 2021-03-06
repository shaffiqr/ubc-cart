// -- Main js file to handle ajax operations
// -- All options and most cart operations are done via nonced ajax functions


var columns,columnsoff;
var magnify_native_width = 0;
var magnify_native_height = 0;

function product_magnify(e) {
	jQuery(document).ready(function($){
		var elem, evt = e ? e:event;
		if (evt.srcElement)  elem = evt.srcElement;
		else if (evt.target) elem = evt.target;
		var elemp = $(elem).parent();
		if(!magnify_native_width && !magnify_native_height){
				magnify_native_width = $(elem).data('width');
				magnify_native_height = $(elem).data('height');
	   } else {
				var magnify_offset = elemp.offset();
				var mx = e.pageX - magnify_offset.left;
				var my = e.pageY - magnify_offset.top;
				if(mx < elemp.width() && my < elemp.height() && mx > 0 && my > 0) {
					 elemp.find(".largeimg").fadeIn(100);
				} else {
					 elemp.find(".largeimg").fadeOut(100);
					 magnify_native_width = 0;
					 magnify_native_height = 0;
				}
				if($(elem).parent().find(".largeimg").is(":visible")) {
					 var rx = Math.round(mx/elemp.find(".smallimg").width()*magnify_native_width - elemp.find(".largeimg").width()/2)*-1;
					 var ry = Math.round(my/elemp.find(".smallimg").height()*magnify_native_height - elemp.find(".largeimg").height()/2)*-1;
					 var bgp = rx + "px " + ry + "px";
					 var px = mx - elemp.find(".largeimg").width()/2;
					 var py = my - elemp.find(".largeimg").height()/2;
					 elemp.find(".largeimg").css({left: px, top: py, backgroundPosition: bgp});
				}
	   }
	});
}

function filterclick(obj){
	if (obj) {
	jQuery('#mfilters button').removeClass('active');
	jQuery(obj).addClass('active');
	var filterValue = jQuery(obj).attr('data-filter');
	jQuery('#iso-container').isotope({ filter: filterValue });
	}
}

// -- chooseform - sets option for formid to be used on checkout page - option
function chooseform(element){
	formid = jQuery(element).val();
		//alert(cart_script_vars.pluginsUrl + '/ubc-cart/assets/img/ajax-loader.gif');
		 jQuery.ajax({
			 url: cart_script_vars.ajaxurl,
			 type: 'POST',
			 data: {action: 'cart_switch_form_action',cart_switch_form_action_nonce: cart_script_vars.cart_switch_form_action_nonce,js_data_for_php: formid},
			 error: function(jqXHR, textStatus) {alert(textStatus);},
			 beforeSend: function(){
		jQuery(element).parent().append('<img style="width:15px;margin-left:5px;" id="spinner" src="'+ cart_script_vars.pluginsUrl +'/ubc-cart/assets/img/ajax-loader.gif">');
		 },
			 dataType: 'html',
			 success: function(response){
		jQuery('#spinner').remove();
		 }
		 });
		 return false;
}

function make_cart_sortable(){
// Return a helper with preserved width of cells  
var fixHelper = function(e, ui) {  
  ui.children().each(function() {  
    jQuery(this).width(jQuery(this).width());  
  });  
  return ui;  
};
	jQuery('#cart-details table.sortable.cartfield_list tbody').sortable({
  		axis: "y",
		//handle: "td",
		cursor: "move",
		helper: fixHelper,
		tolerance: "pointer",
		update: function( event, ui ) {
			var sortedIDs = jQuery(this).sortable( "toArray" );
			jQuery.ajax({
				url: cart_script_vars.ajaxurl,
				type: 'POST',
				data: {action: 'cart_order_action',cart_order_action_nonce: cart_script_vars.cart_order_action_nonce,js_data_for_php: sortedIDs.join()},
				error: function(jqXHR, textStatus) {alert(textStatus);},
				beforeSend: function(){
			 	},
				dataType: 'html',
				success: function(response){
					jQuery('.cartfield_list tbody.ui-sortable tr').each(function(index){
         					jQuery(this).attr('id', index);
      					});
				}
			 });
		}
	}).disableSelection();
}

jQuery( document ).ready(function() {
	//Can we only run below on shortcode pages?
        filterclick(jQuery('#mfilters button.default').get(0));
	make_cart_sortable();


	if (cart_script_vars.maxitems) {
		maxidarr = cart_script_vars.maxitems.split(",");
		for (i = 0; i < maxidarr.length; i++) {
			jQuery('.pid_'+maxidarr[i]).addClass('disabled');
		}
	}
	jQuery('#gform_'+cart_script_vars.formid+' #gform_submit_button_'+cart_script_vars.formid).addClass('cartbtn');
	jQuery('#gform_save_'+cart_script_vars.formid+'_link').addClass('cartbtn').css('display','inline').css('padding','.5em 1.5em');
	if ('' !== cart_script_vars.cartmenu){
		jQuery('li#menu-item-'+cart_script_vars.cartmenu+' a').attr('data-after',cart_script_vars.cartitems);
		jQuery('<style>li#menu-item-'+cart_script_vars.cartmenu+' a:after{content: attr(data-after);color:red;margin-left:3px;}</style>').appendTo('head');
	}

	//Run this only on Archive page - possible - create new js and include only on archive page
	//var $container = jQuery('#isocontainer').isotope({itemSelector: '.element-item',layoutMode: 'fitRows'});

	// -- sets the tax term from drop-down to be used as filter - option
	jQuery('#cartfilter').on('change', function($) {
		  //alert( this.value ); // or $(this).val()
		filter = this.value;
			 jQuery.ajax({
				 url: cart_script_vars.ajaxurl,
				 type: 'POST',
				 data: {action: 'cart_filter_action',cart_filter_action_nonce: cart_script_vars.cart_filter_action_nonce,js_data_for_php: filter},
				 error: function(jqXHR, textStatus) {alert(textStatus);},
				 beforeSend: function(){
			jQuery('#cartfilter').parent().append('<img style="width:15px;margin-left:5px;" id="spinner" src="'+ cart_script_vars.pluginsUrl +'/ubc-cart/assets/img/ajax-loader.gif">');
			 },
				 dataType: 'html',
				 success: function(response){
			jQuery('#spinner').remove();
			 }
			 });
			 return false;
	});

	// toggles whether the menu item shows
	jQuery('#cartmenu_chk').on('change', function($) {
		checked_value = (jQuery(this).prop('checked') ? 1 : 0 ); //false or true
		//alert(checked_value);
			 jQuery.ajax({
					 url: cart_script_vars.ajaxurl,
					 type: 'POST',
					 data: {action: 'cart_menu_action',cart_menu_action_nonce: cart_script_vars.cart_menu_action_nonce,js_data_for_php: checked_value},
					 error: function(jqXHR, textStatus) {alert(textStatus);},
					 beforeSend: function(){
							jQuery('#cartmenu_chk').parent().append('<img style="width:15px;margin-left:5px;" id="spinner" src="'+ cart_script_vars.pluginsUrl +'/ubc-cart/assets/img/ajax-loader.gif">');
				 },
					 dataType: 'html',
					 success: function(response){
						jQuery('#spinner').remove();
					 }
			 });
			 return false;
	});

	// toggles whether the cart is drag and drop
	jQuery('#dandd_chk').on('change', function($) {
		checked_value = (jQuery(this).prop('checked') ? 1 : 0 ); //false or true
		//alert(checked_value);
			 jQuery.ajax({
					 url: cart_script_vars.ajaxurl,
					 type: 'POST',
					 data: {action: 'cart_dandd_action',cart_dandd_action_nonce: cart_script_vars.cart_dandd_action_nonce,js_data_for_php: checked_value},
					 error: function(jqXHR, textStatus) {alert(textStatus);},
					 beforeSend: function(){
							jQuery('#dandd_chk').parent().append('<img style="width:15px;margin-left:5px;" id="spinner" src="'+ cart_script_vars.pluginsUrl +'/ubc-cart/assets/img/ajax-loader.gif">');
				 },
					 dataType: 'html',
					 success: function(response){
						jQuery('#spinner').remove();
					 }
			 });
			 return false;
	});

});

// -- cartSelectColumns - sets the columns in the off and selected state - option
function cartSelectColumns(reset) {
	 var columnstr;
	if (reset){
		alert('reset');
		columnstr = 'reset';
	}
	else{
			columns = [];
			columnstr = '';
			jQuery("#sortable_selected li").each(function () {
				columns.push(this.id);
			});
			columnstr = columns.join(',');

			columnsoff = [];
			columnoffstr = '';
			jQuery("#sortable_available li").each(function () {
				columnsoff.push(this.id);
			});
			columnoffstr = columnsoff.join(',');
			columnstr += '*' + columnoffstr;
	}
	 jQuery.ajax({
		url: cart_script_vars.ajaxurl,
		type: 'POST',
		data: {
		action: 'cart_columns_action',
		cart_columns_action_nonce: cart_script_vars.cart_columns_action_nonce,
		js_data_for_php: columnstr
	},
		error: function(jqXHR, textStatus) {alert(textStatus);},
	beforeSend: function(){
			jQuery('#resetcols').parent().append('<img style="width:15px;margin-left:5px;" id="spinner" src="'+ cart_script_vars.pluginsUrl +'/ubc-cart/assets/img/ajax-loader.gif">');
	},
		dataType: 'html',
		success: function(response){
		jQuery('#spinner').remove();
		jQuery('#cart-details').html('<p>'+ response +'</p>');
		if (reset)
			location.reload(true);
	}
	 });
}

// -- cart_delete_item - called from cart and form to delete item or reduce quantity
// -- onform parameter to handle page refresh for calculation fields to work
function cart_delete_item(element, itemnum, onform){
	 rownum = jQuery(element).parent().parent().index() + 1;
	 jQuery.ajax({
			 url: cart_script_vars.ajaxurl,
			 type: 'POST',
			 data: {action: 'cart_delete_item_action',cart_delete_item_action_nonce: cart_script_vars.cart_delete_item_action_nonce,js_data_for_php: rownum},
			 error: function(jqXHR, textStatus) {alert(textStatus);},
			 beforeSend: function(){
			jQuery(element).attr('src',cart_script_vars.pluginsUrl+'/ubc-cart/assets/img/ajax-loader.gif');
		 },
			 dataType: 'html',
			 success: function(response){
				jQuery(element).attr('src', cart_script_vars.pluginsUrl +'/gravityforms/images/remove.png');
				var resarr = response.split("*");
				takeaction = resarr[0];
				quantcol = resarr[1]*1 + 1;
				itemrow = resarr[2];
				if (takeaction == 'remove'){
					var tr = jQuery('.cartfield_list tr:nth-child('+ itemrow+')');
					tr.remove();
				}
				if (takeaction == 'reduce'){
					var quantval = jQuery('.cartfield_list tr:nth-child('+ itemrow+') td:nth-child('+quantcol+') ').html();
					quantval-- ;
					jQuery('.cartfield_list tr:nth-child('+ itemrow+') td:nth-child('+quantcol+')').html(quantval);
				}
				jQuery('#cart-details .cartinput_list').parent().html(resarr[3]);
				cart_script_vars.cartitems = resarr[4];
				jQuery('li#menu-item-'+cart_script_vars.cartmenu+' a').attr('data-after',cart_script_vars.cartitems);
				if (resarr[5])
					jQuery('.pid_'+resarr[5]).removeClass('disabled');
				make_cart_sortable();
			 }
	 });
	 return false;
}

// -- showcart - used on settings page as debug
function showcart(){
	 jQuery.ajax({
			 url: cart_script_vars.ajaxurl,
			 type: 'POST',
			 data: {action: 'cart_show_action',cart_show_action_nonce: cart_script_vars.cart_show_action_nonce},
			 error: function(jqXHR, textStatus) {alert(textStatus);},
			 //beforeSend: function(){alert('clicked');},
			 dataType: 'html',
			 success: function(response){
		//alert(response);
		jQuery('#cart-details').html('<p>'+ response +'</p>');
		 }
	 });
	 return false;
}

// -- save cart name - if name/label changed in options
function savecartname(){
	 jQuery.ajax({
		url: cart_script_vars.ajaxurl,
		type: 'POST',
		data: {action: 'cart_savename_action',cart_savename_action_nonce: cart_script_vars.cart_savename_action_nonce,js_data_for_php: jQuery('#cartname').val()},
		error: function(jqXHR, textStatus) {alert(textStatus);},
		beforeSend: function(){
			jQuery('#cartname').parent().append('<img style="width:15px;margin-left:5px;" id="spinner" src="'+ cart_script_vars.pluginsUrl +'/ubc-cart/assets/img/ajax-loader.gif">');
		},
		dataType: 'html',
		success: function(response){
			//alert(response);
			jQuery('#status_cartname').text("Cart page exists so, clicking save will change the page title.");
			//change status on settings page to "Cart page exists so, clicking save will change the page title."
			jQuery('#cartmenu_chk').removeAttr("disabled");
			jQuery('#cartmenu_option').show();
			jQuery('#spinner').remove();
		}
	 });
	 return false;
}


// -- save cart button text - if name/label changed in options
function savecartbtn(){
	 jQuery.ajax({
		url: cart_script_vars.ajaxurl,
		type: 'POST',
		data: {action: 'cart_savebtn_action',cart_savebtn_action_nonce: cart_script_vars.cart_savebtn_action_nonce,js_data_for_php: jQuery('#cartbtn').val()},
		error: function(jqXHR, textStatus) {alert(textStatus);},
		beforeSend: function(){
			jQuery('#cartbtn').parent().append('<img style="width:15px;margin-left:5px;" id="spinner" src="'+ cart_script_vars.pluginsUrl +'/ubc-cart/assets/img/ajax-loader.gif">');
		},
		dataType: 'html',
		success: function(response){
			jQuery('#spinner').remove();
		}
	 });
	 return false;
}

// -- deletecart - used on settings page as debug - also need to add this to cart
function deletecart(){
	 jQuery.ajax({
			 url: cart_script_vars.ajaxurl,
			 type: 'POST',
			 data: {action: 'cart_delete_action',cart_delete_action_nonce: cart_script_vars.cart_delete_action_nonce},
			 error: function(jqXHR, textStatus) {alert(textStatus);},
			 beforeSend: function(){},
			 dataType: 'html',
			 success: function(response){
				jQuery('#cart-details .cartinput_list').parent().html('<p>'+ response +'</p>');
				cart_script_vars.cartitems = 0;
				jQuery('li#menu-item-'+cart_script_vars.cartmenu+' a').attr('data-after',cart_script_vars.cartitems);
				jQuery('.cartbtn:not(.by-filter)').removeClass('disabled');
			 }
	 });
	 return false;
}

// -- reset_settings - reset to defaults
function reset_settings(){
	 jQuery.ajax({
			 url: cart_script_vars.ajaxurl,
			 type: 'POST',
			 data: {action: 'cart_reset_settings_action',cart_reset_settings_action_nonce: cart_script_vars.cart_reset_settings_action_nonce},
			 error: function(jqXHR, textStatus) {alert(textStatus);},
			 beforeSend: function(){},
			 dataType: 'html',
			 success: function(response){alert('settings reset');}
	 });
	 return false;
}

// -- addtocart - Adds item with postids to cart
function addtocartmultiple(objmult, postidarr){
	postids = postidarr.split(',');
	//alert(postids.length);
	for (i = 0; i < postids.length; i++) { 
		obj = (jQuery('.pid_'+postids[i]).get());
    		addtocart(obj,postids[i]);
	}
}

// -- addtocart - Adds item with postid to cart
function addtocart(obj,postid){
	 jQuery.ajax({
		url: cart_script_vars.ajaxurl,
		async: false,
		type: 'POST',
		data: {
		action: 'cart_add_action',
		cart_add_action_nonce: cart_script_vars.cart_add_action_nonce,
		js_data_for_php: postid
		 },
			 error: function(jqXHR, textStatus) {alert(textStatus);},
			 beforeSend: function(){
				if (!jQuery(obj).hasClass('disabled')){
					jQuery(obj).find('i').removeClass('icon-shopping-cart');
					jQuery(obj).find('i').addClass('icon-spinner icon-spin');
				}
			  },
			 dataType: 'html',
			 success: function(response){
				var resarr = response.split("*");
				jQuery('#cart-details .cartinput_container').parent().html('<p>'+ resarr[0] +'</p>');
				cart_script_vars.cartitems = resarr[1];
				jQuery('li#menu-item-'+cart_script_vars.cartmenu+' a').attr('data-after',cart_script_vars.cartitems);
				if (!jQuery(obj).hasClass('disabled')){
					jQuery(obj).find('i').removeClass('icon-spinner icon-spin');
					jQuery(obj).find('i').addClass('icon-shopping-cart');
				}
				if (resarr[2])
					jQuery('.pid_'+resarr[2]).addClass('disabled');
				make_cart_sortable();
		 }
	 });
	 return false;
 }


function mygformDeleteListItem(element, max){
	alert('override this');
	var tr = jQuery(element).parent().parent();
	var parent = tr.parent();
	tr.remove();
	mygformToggleIcons(parent, max);
	gformAdjustClasses(parent);
}

function mygformToggleIcons(table, max){
	var rowCount = table.children().length;
	table.find(".delete_list_item").css("visibility", "visible");
}

function gformDeleteListItem(element, max){
	alert('override this');
		var tr = jQuery(element).parent().parent();
		var parent = tr.parent();
		tr.remove();
		gformToggleIcons(parent, max);
		gformAdjustClasses(parent);
}

function gformAdjustClasses(table){
	var rows = table.children();
	for(var i=0; i<rows.length; i++){
		var odd_even_class = (i+1) % 2 === 0 ? "gfield_list_row_even" : "gfield_list_row_odd";
		jQuery(rows[i]).removeClass("gfield_list_row_odd").removeClass("gfield_list_row_even").addClass(odd_even_class);
	}
}
