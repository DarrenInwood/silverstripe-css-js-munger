/*
 * Image Preloader - jQuery Plugin
 * Shows loading text while caching loaders
 *
 * Copyright (c) 2011 Roger 'SparK' Rodrigues da Cruz and Mauricio José Rodrigues
 *
 * Version: 0.8 (01/04/2011)
 * Requires: jQuery v1.3+
 *
 * Dual licensed under GPL and MIT:
 *   http://www.gnu.org/licenses/gpl.html
 *   http://www.opensource.org/licenses/mit-license.php
 */
$(function(){
	$.fn.createLoader = function(options){
		var defaults = {
			effect:"fadeIn",
			effectSpeed:"slow",
			backgroundColor:"#232323",
			useLoadingImage:true,
			backgroundImage:"images/loading.gif",
			backgroundPosition:"center center",
			backgroundRepeat:"no-repeat",
			textLoading:"carregando...",
			textColor:"#FFFFFF"
		};
		//joining objects
		var options = $.extend(defaults, options);
		
		//for every element jquery gives us we apply the rule.
		this.each(preloadImage);
		
		//the rule itself
		function preloadImage(){
			//creating var to access 'this' without jquery influence.
			var target = $(this);
			//this will preload our image and tell us when it's ready.
			var loader = new Image();
			
			//setting callback function for the loader.
			loader.onload = loadTarget;
			
			//storing image information.
			var path = target.attr("src");
			var width = target.attr("width");
			var height = target.attr("height");
			
			//div to hold the image tag and show loading animation.
			var holder = $('<div>'+options.textLoading+'</div>');
			
			//making the div act like an img tag, putting loading animation on background.
			holder.css("background-color", options.backgroundColor);
			holder.css("color",options.textColor);
			if(options.useLoadingImage){	
				holder.css("background-image"," url('"+ options.backgroundImage +"')");
				holder.css("background-position", options.backgroundPosition);
				holder.css("background-repeat",options.backgroundRepeat);
				holder.css("color",options.backgroundColor);
			}
			holder.css("margin","0");
			holder.css("padding","0");
			holder.css("display","inline-block");
			
			//replacing the img tag with our div.
			holder.insertBefore(target);
			
			//put img inside our div
			target.prependTo(holder);
			//hide it
			target.css("display","none");
			
			//this causes the loader to work and grab the file for us.
			loader.src = path;
			
			//if img tag has no width and height, we grab it from the image loader.
			width = width ? width : loader.width;
			height = height ? height : loader.height;
			
			//keeping things the same size they were before.
			holder.css("width",width+"px");
			holder.css("height",height+"px");
			target.attr("width",width);
			target.attr("height",height);
			
			//when image loads, we put it back outside, with style.
			function loadTarget(){
				//put information back on.
				target.attr("src",path);
				
				//fade in, throw it outside and remove the holder div.
				target[options.effect](options.effectSpeed,function(){
					target.insertBefore(holder);
					holder.remove();
				});
			}
		}
	}
});