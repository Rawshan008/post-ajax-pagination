(function($) {
    'use strict';

    jQuery(document).ready(function() {


        /**
         * Ajax Filter
         */
        $(".pf-filter").on("click",function () {
            var term_id = $(this).attr('data_id');

            if (!$(this).hasClass('active')) {
                $(this).addClass('active').siblings().removeClass('active');
                pf_ajax_load(term_id, $(this));

            }
        });

        /**
         * Ajax Pagination
         */
        $( document ).on('click', '#am_posts_navigation_init a.page-numbers, .am-post-grid-load-more', function(e){
            e.preventDefault();

            // let urlParams = GetURLParameter(location.search);

            var term_id = "-1";
            var paged = $(this).text();

            var loadMore = false;


            // Try infinity loading
            if ( $(this).hasClass('am-post-grid-load-more') ) {
                paged = $(this).data('next');
                loadMore = true;
            } else {
                var getUrl = $(this).attr('href');

                var pageNumber =1;
                var urlSplit = getUrl.split('?paged=');
                if(urlSplit.length >1) {
                    pageNumber = urlSplit[1];
                }

                var paged = pageNumber;
            }

            var theSelector = $(this).closest('.pf-post-wrapper').find('.pf-filter');
            var activeSelector = $(this).closest('.pf-post-wrapper').find('.pf-filter.active');

            if( activeSelector.length > 0 ){
                term_id = activeSelector.attr('data_id');
            } else {
                activeSelector = theSelector;
            }

            // Load Posts
            pf_ajax_load(term_id, activeSelector, paged, loadMore);

            //console.log(pageNow,activeSelector,term_id);

        });

        /**
         * post loaded
         */
        function pf_ajax_load(term_ID, selector, paged, loadMore) {

            var pagination_type = $('.pf-post-wrapper').attr("data-pagination_type");
            var jsonData = $(selector).closest('.pf-post-wrapper').attr('data-pf_post_grid');

            var $args = JSON.parse(jsonData);

            var data = {
                action: 'pf_post_filter',
                pf_ajax_nonce: pf_ajax_params.pf_ajax_nonce,
                term_ID: term_ID,
                jsonData: jsonData,
                pagination_type: pagination_type,
                loadMore: loadMore,
            }

            if( paged ){
                data['paged'] = paged;
            }

            $.ajax({
                type: 'post',
                url: pf_ajax_params.pf_ajax_url,
                data: data,
                beforeSend: function ($data) {
                    if (loadMore) {
                        $(selector).closest('.pf-post-wrapper').find('.am-post-grid-load-more').addClass('loading');
                    } else {
                        $(selector).closest('.pf-post-wrapper').find('.asr-loader').show();
                    }
                },
                complete: function (data) {
                    if (loadMore) {
                        $(selector).closest('.pf-post-wrapper').find('.am-post-grid-load-more').removeClass('loading');
                    } else {
                        $(selector).closest('.pf-post-wrapper').find('.asr-loader').hide();
                    }
                },
                success: function (data) {
                    if (loadMore) {
                        var newPosts = $('.pf-post-layout' ,data).html();
                        var newPagination = $('.pf-post-pagination', data).html();

                        $(selector).closest('.pf-post-wrapper').find('.pf-post-result .pf-post-layout').append(newPosts);
                        $(selector).closest('.pf-post-wrapper').find('.pf-post-result .pf-post-pagination').html(newPagination);
                    } else {
                        $(selector).closest('.pf-post-wrapper').find('.pf-post-result').hide().html(data).fadeIn(0, function() {
                            $(this).html(data).fadeIn(300);
                        });
                    }
                }
            });

            // console.log(pagination_type);
        }

        /**
         * Initial  custom trigger
         */
        $(document).on('pf_ajax_post_grid_init', function(){

            $('.pf-post-wrapper').each(function(i,el){
                var initData = $(this).data('pf_post_grid');
                if(initData && initData.initial){
                    pf_ajax_load(initData.initial,$(this).find('.pf-post-container'));
                }
            });
        });
    });

    $(window).load(function(){
        $(document).trigger('pf_ajax_post_grid_init');
    });

})(jQuery);
