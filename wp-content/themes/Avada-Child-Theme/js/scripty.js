jQuery(document).ready(function(){

        /* supprimer les titles des images sur survol */
        jQuery('img').removeAttr('title');
        jQuery('.wpmf-gallery-item a').removeAttr('title');

        /* remplacer texte read more (shortcode) */
        jQuery('.fusion-read-more').text('lire la suite');

        /* regler probleme flou des images gallery slider */
        jQuery('.gallery.carousel img').attr('style', 'object-fit: cover;left: 0px !important;right: 0px !important;min-height: 320px !important;max-height: 320px !important;');

   
       
    /********************toggele click*********************/
       
   var acc = document.getElementsByClassName("slid_in");
   var iac;
   for (iac = 0; iac < acc.length; iac++) {
   acc[iac].addEventListener("click", function() { 
   this.classList.toggle("active");
   var allcontent = this.nextElementSibling;  
   if (allcontent.style.maxHeight){
         allcontent.style.maxHeight = null;
       } else {
         allcontent.style.maxHeight =  "900px";
       }
    
     });
   }


       /* svg logo footer */
        jQuery('.coherence-logo img.svg').each(function(){
            var $img = jQuery(this);
            var imgID = $img.attr('id');
            var imgClass = $img.attr('class');
            var imgURL = $img.attr('src');
            jQuery.get(imgURL, function(data) {
                // Get the SVG tag, ignore the rest
                var $svg = jQuery(data).find('svg');
                // Add replaced image's ID to the new SVG
                if(typeof imgID !== 'undefined') {
                    $svg = $svg.attr('id', imgID);
                }
                // Add replaced image's classes to the new SVG
                if(typeof imgClass !== 'undefined') {
                    $svg = $svg.attr('class', imgClass+' replaced-svg');
                }
                // Remove any invalid XML tags as per http://validator.w3.org
                $svg = $svg.removeAttr('xmlns:a');
                // Replace image with new SVG
                $img.replaceWith($svg);
            });
        });


        /* single galerie image responsive */
        function responsiveFlexslider () {
                var windowsize = jQuery(window).width();
                if (windowsize <=497) {
                    // change data-wpmfcolumns from default value to 1
                    jQuery( ".flexslider" ).data( "wpmfcolumns", 1 );
                    // add class of one column to li
                    jQuery('.flexslider .slides.wpmf-slides > li').addClass('wpmf-gg-one-columns');
                    // Reinitialize FlexSlider
                    jQuery('.flexslider').flexslider({
                        animation: "slide",
                        animationLoop: true,
                        smoothHeight: true,
                        pauseOnHover: true,
                        slideshowSpeed: 5000,
                        prevText: "",
                        nextText: "",
                        itemWidth: 0,
                        start: function () {jQuery('.entry-content').removeClass('loading');}
                    });
                }
        }

        responsiveFlexslider();
});


