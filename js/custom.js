$(document).ready(function(){
	
	var html = $('section.proposal_info').html();
	$('section.proposal_info').css('display', 'none');
	$('div.user_info').html(html);
	
	$('.user_info select[name="area"]').change(function(){
		$('.proposal_info select[name="area"]').val($(this).val());
	});
	
	$('.user_info input[name="proponent"]').keyup(function(eventObject){
		$('.proposal_info input[name="proponent"]').val($(this).val());
	});	
	
	// $('section.proposal_info').html('');

//hide, show text
		if (jQuery('.users').height() >= 20) {

			jQuery('.users').toggleClass('open');
			jQuery('.users').on('click', function(){
				jQuery(this).toggleClass('active');
				jQuery(this).parents('.warp_users').toggleClass('act');
				if(jQuery(this).hasClass('active')){
					jQuery('.users').addClass('active');
					var allHeight = "auto";
					var startWidth = "calc(100% - 460px)";
					jQuery(this).parents('.warp_users').css('height',allHeight);
					jQuery(this).parents('.warp_users').css('width',startWidth);
				}
				else{
					jQuery(this).parents('.warp_users').css('height',20);
					jQuery(this).parents('.warp_users').css('width',startWidth);
				}
			});
		}

/*	if (jQuery('.user_w input[type="submit"]').length > 0) {
		var input = jQuery('.user_w input[type="submit"]').get(0).outerHTML;
		jQuery('.user_w input[type="submit"]').css('display', 'none');
		jQuery("#copy_user_w").append(input);
		jQuery('#copy_user_w input[type="submit"]').on('click', function(){
			alert('jhdfjksd');
//			jQuery('.user_w input[type="submit"]').click();
		});
	} */
	
//hide, show text in widget
	if (jQuery('.user_w').height() >= 130) {
		var maxHeight = "100%";
		jQuery('.user_w').css('max-height',maxHeight);
		jQuery('.user_w').css('height',130);
		jQuery('.user_w').toggleClass('max');
		jQuery('.user_w').on('click', function(){
			jQuery(this).toggleClass('active');
			jQuery('.user_w').toggleClass('open');
			if(jQuery(this).hasClass('active')){
				var allHeight = "auto";
				jQuery(this).css('height',allHeight);
			}
			else{
				jQuery(this).css('height',130);
			}
		});	    
	}
	if (jQuery('.user_w input[type="submit"]').length > 0) {

			jQuery('.user_w').toggleClass('active');
			jQuery('.user_w').toggleClass('open');
			var allHeight = "auto";
			jQuery('.user_w').css('height',allHeight);
	} 
		

	
	

	// Top scroll IN TABLE

	function DoubleScroll(element) {
	var scrollbar= document.createElement('div');
	scrollbar.appendChild(document.createElement('div'));
	scrollbar.style.overflow= 'auto';
	scrollbar.style.overflowY= 'hidden';
	scrollbar.firstChild.style.width= element.scrollWidth+'px';
	scrollbar.firstChild.style.paddingTop= '1px';
	scrollbar.firstChild.appendChild(document.createTextNode('\xA0'));
	scrollbar.onscroll= function() {
	    element.scrollLeft= scrollbar.scrollLeft;
	};
	element.onscroll= function() {
	    scrollbar.scrollLeft= element.scrollLeft;
	};
	    element.parentNode.insertBefore(scrollbar, element);
	}
	DoubleScroll(document.getElementById('doublescroll'));

		
});

 