$( document ).ready(function() {
  if (typeof sambaai !== 'undefined') {
    if (sambaai.samba_widget_style != 0) {
      sambaSwiper();
      sambaPersonaliser(sambaai.samba_widget_style);
    }
  }

});

function sambaSwiper(){
  const swiper = new Swiper('.swiper-sambaai', {
    // Optional parameters
    loop: true,
    slidesPerView: 2,
    spaceBetween: 10,
    // Navigation arrows
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },
    breakpoints: {
    1200:{
      slidesPerView: 4,
    },

    992:{
      slidesPerView: 4,
      },
    768:{
      slidesPerView: 3,
      },
    }
  });
}

function sambaPersonaliser(style){
  var yPers = diffAnalytics.personaliser("homePageCampain");
  yPers.personalisedHomepage({ count: 10 }, function (err, result){

       if (err) {
            console.log('Tutaj return tpl z widgetem na sztywno');
       }

       else {
         switch (style) {
         	case '1':
            sambaStyl1(result.recommendation);
         		console.log('Style 1');
         		break;
         	case '2':
         		console.log('Style 2');
         		break;
         	default:
         		console.log('default');
         }

       }
  });
}

function sambaStyl1(result){
        $.each(result, function(key, value) {
          if(typeof value.formattedPrice_before !== 'undefined'){
            $('<div class="swiper-slide h-auto flex-wrap d-flex"><article class="product-miniature  style-1 hover-mobile"><div class="product-container"><div class="thumbnail-container"><div class="thumbnail-inner"><a href="'
            + value.url +
            '"><img src="'
            + value.image +
            '" /></a></div></div><div class="product-description"><p class="h3 product-title"><a href="'
            + value.url +'">'
            + value.name +
            '</a></p><div class="product-price-and-shipping"><span class="sr-only">'
            + 'Cena Regularna' +
            '</span><span class="regular-price">'
            + value.formattedPrice_before +
            '</span><span class="sr-only">'
            + 'Cena' +
            '</span><span class="price current-price-discount">'
            + value.formattedPrice + '</span></div></div></div></article><div>').appendTo('.container-sambaai .swiper-wrapper');
          } else {
            $('<div class="swiper-slide h-auto flex-wrap d-flex"><article class="product-miniature  style-1 hover-mobile"><div class="product-container"><div class="thumbnail-container"><div class="thumbnail-inner"><a href="'
            + value.url + '"><img src="'
            + value.image +
            '" /></a></div></div><div class="product-description"><p class="h3 product-title"><a href="'
            + value.url +'">'
            + value.name +
            '</a></p><div class="product-price-and-shipping"></span><span class="sr-only">'
            + 'Cena' +
            '</span><span class="price current-price-discount">'
            + value.formattedPrice + '</span></div></div></div></article><div>').appendTo('.container-sambaai .swiper-wrapper');
          }
        });
        console.log(result);
}
