"use strict";
(function ($) {
  // ==========================================
  //      Start Document Ready function
  // ==========================================
  $(document).ready(function () {
    //============================ Scroll To Top Icon Js Start =========
    (() => {
      const btn = $(".scroll-top");
      $(window).on("scroll", function () {
        if ($(window).scrollTop() >= 100) {
          $(".header").addClass("fixed-header");
          btn.addClass("show");
        } else {
          $(".header").removeClass("fixed-header");
          btn.removeClass("show");
        }
      });

      btn.on("click", function (e) {
        e.preventDefault();
        $("html, body").animate(
          {
            scrollTop: 0,
          },
          "300"
        );
      });
    })();

    // ========================== Add Attribute For Bg Image Js Start =====================
    $(".bg-img").css("background-image", function () {
      return `url(${$(this).data("background-image")})`;
    });
    // ========================== Add Attribute For Bg Image Js End =====================

    // ================== Password Show Hide Js Start ==========
    $(".toggle-password").on("click", function () {
      $(this).toggleClass("fa-eye");
      var input = $($(this).attr("id"));
      if (input.attr("type") == "password") {
        input.attr("type", "text");
      } else {
        input.attr("type", "password");
      }
    });
    // =============== Password Show Hide Js End =================

    // ================== Sidebar Menu Js Start ===============
    // Sidebar Dropdown Menu Start
    $(".has-dropdown > a").click(function () {
      $(".sidebar-submenu").slideUp(200);
      if ($(this).parent().hasClass("active")) {
        $(".has-dropdown").removeClass("active");
        $(this).parent().removeClass("active");
      } else {
        $(".has-dropdown").removeClass("active");
        $(this).next(".sidebar-submenu").slideDown(200);
        $(this).parent().addClass("active");
      }
    });
    // Sidebar Dropdown Menu End

    // Sidebar Icon & Overlay js
    $(".navigation-bar").on("click", function () {
      $(".sidebar-menu").addClass("show-sidebar");
      $(".sidebar-overlay").addClass("show");
    });

    $(".sidebar-menu__close, .sidebar-overlay").on("click", function () {
      $(".sidebar-menu").removeClass("show-sidebar");
      $(".sidebar-overlay").removeClass("show");
    });

    // Sidebar Icon & Overlay js
    // ===================== Sidebar Menu Js End =================

    $("[data-highlight-word]").each((index, el) => {
      var text = $(el).text().trim();

      // Define the range (1-based index)
      var startWord =
        $(el).data("highlight-start") || $(el).data("highlight-word");
      var endWord = $(el).data("highlight-word");

      // Split the string into words
      var words = text.split(" ");

      // Build the result
      var result = "";

      for (var i = 0; i < words.length; i++) {
        if (i === startWord - 1) {
          result += `<span class="text--base d-inline">`;
        }

        result += words[i];

        if (i === endWord - 1) {
          result += "</span>";
        }

        // Add space between words (except after last word)
        if (i < words.length - 1) {
          result += " ";
        }
      }

      // Set the result to an element
      $(el).html(result);
    });

    //Plugin Customization Start
    // ========================= Select2 Js Start ==============
    (() => {
      // select initial
      $(".select2").each(function (index, element) {
        if (!$(element).parent().hasClass("select2-wrapper")) {
          $(element).wrap('<div class="select2-wrapper"></div>');
        }

        $(element).select2({
          dropdownParent: $(element).closest(".select2-wrapper"),
        });
      });
    })();
    // ========================= Select2 Js End ==============

    // ========================= Slick Slider Js Start ==============
    (() => {
      const sliderConfig = {
        slidesToScroll: 1,
        autoplay: false,
        autoplaySpeed: 2000,
        speed: 1500,
        dots: true,
        pauseOnHover: true,
        arrows: false,
        prevArrow:
          '<button type="button" class="slick-prev"><i class="fas fa-long-arrow-left"></i></button>',
        nextArrow:
          '<button type="button" class="slick-next"><i class="fas fa-long-arrow-right"></i></button>',
      };

      $(".client-slider").slick({
        autoplay: true,
        autoplaySpeed: 0,
        speed: 15000,
        arrows: false,
        swipe: false,
        dots: false,
        slidesToShow: 6,
        cssEase: "linear",
        pauseOnFocus: false,
        pauseOnHover: false,

        responsive: [
          {
            breakpoint: 1399,
            settings: {
              slidesToShow: 5,
            },
          },
          {
            breakpoint: 991,
            settings: {
              slidesToShow: 4,
            },
          },
          {
            breakpoint: 768,
            settings: {
              slidesToShow: 3,
            },
          },
          {
            breakpoint: 575,
            settings: {
              slidesToShow: 2,
              variableWidth: true,
            },
          },
        ],
      });

      $(".testimonial-slider").slick({
        infinite: true,
        slidesToShow: 1,
        autoplay: true,
        autoplaySpeed: 0,
        speed: 15000,
        swipe: false,
        dots: false,
        cssEase: "linear",
        pauseOnFocus: false,
        pauseOnHover: false,
        variableWidth: true,
        arrows: false,
      });
    })();
    // ========================= Slick Slider Js End ===================

    // ========================= Odometer Counter Up Js End ==========
    $(".statistics-item").each(function () {
      $(this).isInViewport(function (status) {
        if (status === "entered") {
          for (
            var i = 0;
            i < document.querySelectorAll(".odometer").length;
            i++
          ) {
            var el = document.querySelectorAll(".odometer")[i];
            el.innerHTML = el.getAttribute("data-odometer-final");
          }
        }
      });
    });
    // ========================= Odometer Up Counter Js End =====================

    // ==================== animation JS Start ====================
    new WOW().init();
    // ==================== animation JS End ====================

    // calculate height
    function setHeight(variable, name) {
      let headerSelect = document.getElementsByClassName(`${name}`)[0];
      if (headerSelect) {
        let headerHeight = headerSelect.clientHeight;
        document.documentElement.style.setProperty(
          `${variable}`,
          `${headerHeight}px`
        );
      }
    }
    setHeight("--header-h", "header");

    // tooltips
    const tooltipTriggerList = document.querySelectorAll(
      '[data-bs-toggle="tooltip"]'
    );
    const tooltipList = [...tooltipTriggerList].map(
      (tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl)
    );

    // file upload
    $("#form-file-upload").on("change", function (e) {
      $.each(this.files, function (i, file) {
        if (!file.type.match("image.*")) return;
        let reader = new FileReader();
        reader.onload = function (e) {
          let thumb = $(`
                        <div class="banner-form-thumbs-img">
                        <img src="${e.target.result}" alt="${file.name}">
                        <button class="remove-btn">
                            <i class="las la-times"></i>
                        </button>
                        </div>
                    `);
          thumb.find(".remove-btn").on("click", function () {
            thumb.remove();
          });

          $(".banner-form-thumbs").append(thumb);
        };
        reader.readAsDataURL(file);
      });
      e.target.value = "";
    });
  });

  /* ==================== Gallary Showcase Card Excerpt Shorter JS Start ==================== */

  $(window).on("load", function () {
    $(".gallary-showcase-card").each((index, item) => {
      if ($(item).height() < 300) {
        $(item).addClass("shorter");
      } else {
        $(item).removeClass("shorter");
      }
    });
  });
  /* ==================== Gallary Showcase Card Excerpt Shorter JS End ====================== */

  /* ==================== Banner Form JS Start ====================== */

  /* ==================== Banner Form JS End ====================== */
  $(".banner-form-tab__btn").each(function () {
    $(this).on("click", function () {
      $(".banner-form-tab__btn").removeClass("active");
      $(this).addClass("active");
      $(".banner-form-row").removeClass("active");
      $(".banner-form-row").eq($(this).index()).addClass("active");
    });
  });

  /* ==================== Banner Form JS End ====================== */

  // ==========================================
  //      End Document Ready function
  // ==========================================

  // ========================= Preloader Js Start =====================
  $(window).on("load", function () {
    $(".preloader").fadeOut();
  });
  // ========================= Preloader Js End=====================
})(jQuery);
