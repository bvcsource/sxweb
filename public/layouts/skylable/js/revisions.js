/*
 The contents of this file are subject to the Common Public Attribution License
 Version 1.0 (the "License"); you may not use this file except in compliance with
 the License. You may obtain a copy of the License at
 http://opensource.org/licenses/cpal_1.0. The License is based on the Mozilla
 Public License Version 1.1 but Sections 14 and 15 have been added to cover use
 of software over a computer network and provide for limited attribution for the
 Original Developer. In addition, Exhibit A has been modified to be consistent with
 Exhibit B.

 Software distributed under the License is distributed on an "AS IS" basis, WITHOUT
 WARRANTY OF ANY KIND, either express or implied. See the License for the
 specific language governing rights and limitations under the License.

 The Original Code is the SXWeb project.

 The Original Developer is the Initial Developer.

 The Initial Developer of the Original Code is Skylable Ltd (info-copyright@skylable.com). 
 All portions of the code written by Initial Developer are Copyright (c) 2013 - 2015
 the Initial Developer. All Rights Reserved.

 Contributor(s):    

 Alternatively, the contents of this file may be used under the terms of the
 Skylable White-label Commercial License (the SWCL), in which case the provisions of
 the SWCL are applicable instead of those above.

 If you wish to allow use of your version of this file only under the terms of the
 SWCL and not to allow others to use your version of this file under the CPAL, indicate
 your decision by deleting the provisions above and replace them with the notice
 and other provisions required by the SWCL. If you do not delete the provisions
 above, a recipient may use your version of this file under either the CPAL or the
 SWCL.
 */

if (!FileRevisions) {
    /**
     * Handles file revisions.
     *
     * In the global scope should be defined a variable named 
     * 'current_path' containing the current visited path.
     *
     * @constructor
     */
    var FileRevisions = {
        urls: {
            restore_rev :"/revs"
        },
        self: this,
        title : '',

        // Flag
        is_working: false,

        /**
         * Close and remove the preview lightbox
         * @param e
         */
        removePreview : function(e) {
            $(document).unbind('keydown');
            $(window).unbind('resize');
            $('#preview-overlay, #preview-lightbox, #preview-nav-bar')
                .fadeOut('slow', function(){
                    $(this).remove();
                });
        },
        preview_current: null,
        preview_next: null,
        preview_prev: null,
        preview_index: 0,

        /**
         * Find the next and previous files to preview.
         *
         * If file_url is not null starts showing from that file,
         * else use the FileRevisions.preview_index to do calculations.
         *
         * @param file_url
         */
        previewFindNextPrevElement : function(file_url) {
            var elements = $('.revpreview');

            // Starts from this index
            if (file_url) {
                for(var idx = 0; idx < elements.length; idx++) {
                    var href = $(elements.get(idx)).attr('href');
                    if (href === file_url) {
                        FileRevisions.preview_index = idx;
                        break;
                    }
                }
            }

            if (FileRevisions.preview_index == 0) {
                FileRevisions.preview_prev = null;
            } else {
                FileRevisions.preview_prev = elements.get( FileRevisions.preview_index - 1 );
            }

            if (FileRevisions.preview_index == elements.length - 1) {
                FileRevisions.preview_next = null;
            } else {
                FileRevisions.preview_next = elements.get( FileRevisions.preview_index + 1 );
            }

            FileRevisions.preview_current = elements.get( FileRevisions.preview_index );
        },

        /**
         * Setup the preview navbar actions
         *
         * @param file_url
         */
        previewFileSetupNavbar : function(file_url) {

            $('#preview-nav-bar-close').click(function(e){
                FileRevisions.removePreview();
                e.preventDefault();
            });
            $('#preview-nav-bar-download').click(function(e){
                window.open( $(FileRevisions.preview_current).attr('href') );
                e.preventDefault();
            });

            $('#preview-nav-bar-restore').click(function(e){
                e.preventDefault();
                FileRevisions.removePreview();
                var rev_form = document.getElementById('revisions_form');
                var rev_id = $(FileRevisions.preview_current).data('rev-id');
                $('input:radio[name="rev_id"][value="' + rev_id + '"]').prop('checked', true);
                rev_form.submit();
            });
            
            FileRevisions.previewFindNextPrevElement(file_url);
    
            var elements = $('.revpreview');

            $('#preview-nav-bar-next').click(function(e){
                if (FileRevisions.preview_next !== null) {
                    FileRevisions.previewShowFile($(FileRevisions.preview_next).attr('href'), $(FileRevisions.preview_next).data('filetype') );
                    // Advance the preview index
                    FileRevisions.preview_index++;
                    if (FileRevisions.preview_index >= elements.length) {
                        FileRevisions.preview_index = elements.length - 1;
                    }
                }

                FileRevisions.previewFindNextPrevElement();
                FileRevisions.setupPreviewTitle();
                e.preventDefault();
            });

            $('#preview-nav-bar-prev').click(function(e){
                if (FileRevisions.preview_prev !== null) {
                    FileRevisions.previewShowFile($(FileRevisions.preview_prev).attr('href'), $(FileRevisions.preview_prev).data('filetype'));
                    // Advance the preview index
                    FileRevisions.preview_index--;
                    if (FileRevisions.preview_index < 0) {
                        FileRevisions.preview_index = 0;
                    }
                }
                FileRevisions.previewFindNextPrevElement();
                FileRevisions.setupPreviewTitle();
                e.preventDefault();
            });

            FileRevisions.setupPreviewTitle();
        },
        
        setupPreviewTitle : function() {
            FileRevisions.title = sprintf(Skylable_Lang.revisionsNavBarLabel, $(FileRevisions.preview_current).data("rev-label"));
            $('#preview-nav-bar-title').html(FileRevisions.title);
        },

        /**
         * Preview a file into the browser window
         *
         * @param file_url the complete URL to show
         * @param file_type the file type
         */
        previewFile : function(file_url, file_type) {

            $('<div id="preview-overlay"></div>')
                .css('opacity', '0')
                .animate({'opacity': '0.5'}, 'slow')
                .click(FileRevisions.removePreview)
                .appendTo('body');
            $('<div id="preview-lightbox"></div>')
                .hide()
                .appendTo('body');

            $('<div id="preview-nav-bar">' +
                '<span class="preview-nav-bar-text" id="preview-nav-bar-title">'+ FileRevisions.title +'</span>' +
            '<a class="preview-nav-bar-btn" id="preview-nav-bar-close">' + Skylable_Lang.revisionsCloseBtn + '</a>' +
            '<a class="preview-nav-bar-btn" id="preview-nav-bar-download">' + Skylable_Lang.revisionsDownloadBtn + '</a>' +
            '<a class="preview-nav-bar-btn" id="preview-nav-bar-restore">' + Skylable_Lang.revisionsRestoreBtn + '</a>' +
            '<a class="preview-nav-bar-btn" id="preview-nav-bar-prev">' + Skylable_Lang.revisionsPrevBtn + '</a>' +
            '<a class="preview-nav-bar-btn" id="preview-nav-bar-next">' + Skylable_Lang.revisionsNextBtn + '</a>' +
            '</div>')
                .hide()
                .appendTo('body');

            FileRevisions.previewFileSetupNavbar(file_url);
            FileRevisions.previewShowFile(file_url, file_type);
            

            // Bind the ESC key to close the window
            $(document).keydown(function(event){
                if (event.which == 27) {

                    FileRevisions.removePreview(event);

                    $(document).unbind('keydown', this);
                }
            });

            $(window).resize(function(event){
                var pnb = $('#preview-nav-bar');
                var lb = $('#preview-lightbox');
                $(lb).css({
                    'top' : 20 + $(pnb).height(),
                    'width' : $(window).width() - 40,
                    'height': $(window).height() - 60
                }).css({
                    'left': (($(window).width() - $(lb).width()) / 2)
                })
            });
        },

        /**
         * Do the effective file preview, need that everything is already set up
         * by FileRevisions.previewFile
         * @param file_url the file to show
         * @param file_type the file type
         */
        previewShowFile : function(file_url, file_type) {


            var pnb = $('#preview-nav-bar');
            $(pnb)
                .css({
                    'top' : 0,
                    'width': '100%',
                    'margin' : '0 auto',
                    'padding' : '4px 0',
                    'text-align' : 'center'
                });

            var lb = $('#preview-lightbox');
            $(lb).empty()
                .css({
                    'top' : 20 + $(pnb).height(),
                    'width' : $(window).width() - 40,
                    'height': $(window).height() - 60,
                    'background' : 'transparent',
                    'overflow' : 'hidden'
                }).css({
                    'left': (($(window).width() - $(lb).width()) / 2)
                });

            $('#preview-overlay').css('background-image','');

            if (file_type === 'pdf') {
                $('<iframe id="preview-pdf"></iframe>')
                    .attr('src', '/pdfjs/web/viewer.html?file='+encodeURIComponent(file_url) + '&_c=' + Math.random())
                    .css('width', '100%')
                    .appendTo(lb);

                $('#preview-pdf').css('height', '100%' );

                $(lb).fadeIn();
                $(pnb).fadeIn();

            } else if(file_type === 'source' || file_type === 'text') {

                $.ajax({
                    url : file_url,
                    
                    cache : false,
                    success: function(data, status, xhr) {
                        $.getScript('/google-code-prettify/run_prettify.js');

                        $('<pre class="prettyprint" id="preview-source"></pre>')
                            .css('width','100%')
                            .css('background', '#fff')
                            .appendTo('#preview-lightbox');

                        $('#preview-source').html( data.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') );

                        $(lb).css({
                            'background': '#fff',
                            'overflow' : 'auto',
                            'text-align' : 'left',
                            'vertical-align' : 'top'
                        }).fadeIn();

                        $(pnb).fadeIn();
                    },
                    error : function (xhr, status) {
                        var dlg = FileRevisions.getDialog(Skylable_Lang.revisionsErrorTitle);
                        dlg.html('<p>'+Skylable_Lang.revisionsLoadFailed+'</p>');
                        dlg.dialog('option', 'buttons', [{
                            text: Skylable_Lang['closeBtn'],
                            click:function(e) {
                                dlg.dialog('close');
                                FileRevisions.removePreview();
                            }
                        }]);
                        dlg.dialog('open');
                    }
                });
            } else {
                $('<span id="preview-img-container"></span>')
                    .css({
                        'display' :'inline-block',
                        'height' : '100%',
                        'vertical-align' : 'middle'
                    }).appendTo(lb);
                $('<img />')
                    .on('error', function(ev){
                        var dlg = FileRevisions.getDialog(Skylable_Lang.revisionsErrorTitle);
                        dlg.html('<p>'+Skylable_Lang.revisionsLoadFailed+'</p>');
                        dlg.dialog('option', 'buttons', [{
                            text: Skylable_Lang['closeBtn'],
                            click:function(e) {
                                dlg.dialog('close');
                                FileRevisions.removePreview();
                            }
                        }]);
                        dlg.dialog('open');
                    })
                    .on('load',function(ev){
                        $('#preview-overlay').css('background-image','none');
                        $(lb).css({
                            'text-align' : 'center'
                        }).fadeIn();

                        $(pnb).fadeIn();
                    })
                    .click(FileRevisions.removePreview)
                    .attr('src', file_url + '?' + Math.random())
                    .css({ 'max-width' : '100%', 'max-height' : '100%', 'vertical-align' : 'middle' })
                    .appendTo(lb);
            }
        },
        
        /**
         * Get the base dialog.
         *
         * @param title dialog title
         * @returns {*|jQuery|HTMLElement}
         */
        getDialog : function(title) {
            var dlg = $('#dialog');
            dlg.dialog({
                autoOpen: false,
                modal: true,
                resizable: true,
                title: title,
                beforeClose: function(ev, ui) {
                    // Avoids closing while AJAX calls
                    if (FileRevisions.is_working) {
                        return false;
                    }
                }
            });
            return dlg;
        },
        
        /**
         * Assigns event handlers on the entire page
         */
        assignHandlers : function() {
            FileRevisions.assignRevisionsListHandlers ();
        },

        /**
         * Assigns all the file list event handlers
         */
        assignRevisionsListHandlers : function() {
            $('.revpreview').click(function(e){

                var type = $(this).data('filetype');
                if (type === 'data') {
                    return true;
                } else {
                    FileRevisions.previewFile($(this).attr('href'), type);
                    e.preventDefault();
                }
            });
        },

        /**
         * Manages an expired user.
         *
         * @param dialog
         * @param xhr
         * @returns {boolean}
         */
        expiredUser : function(dialog, xhr) {
            if (xhr.status === 403) {
                var type = xhr.getResponseHeader('Content-Type');
                if (type === 'application/json') {
                    var data = JSON.parse(xhr.responseText);

                    dialog.html('<p>' + data.error + '</p>');
                    dialog.dialog({
                        buttons:[{
                            text : Skylable_Lang['doLoginBtn'],
                            click : function (e) {
                                window.location.href = data.url;
                            }
                        }]
                    });

                } else {
                    dialog.html(xhr.responseText);
                    dialog.dialog({
                        buttons:[{
                            text : Skylable_Lang['closeBtn'],
                            click : function (e) {
                                FileRevisions.is_working = false;
                                dialog.dialog('close');
                            }
                        }]
                    });
                }
                return true;
            }
            return false;
        }
    }
}