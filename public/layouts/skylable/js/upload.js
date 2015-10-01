
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


/**
 * Handle file uploads.
 *
 * Variables the you must define elsewhere:
 * 'upload_url' - string, the URL to call for uploading files
 * 'maxFileSize' - integer - maximum accepted file size in bytes
 * 'current_path' - string - current destination path
 *
 * You must include jQuery, jQueryUI, sprintf.js, the language file
 * and the file_operations.js before this file.
 */

if (!Skylable_Uploads) {
	/**
	 * Holds the parameters for the upload handler
	 * 
	 */
	var Skylable_Uploads = {
		is_working : false,
		dlg : null,
		has_errors : false,
        upload_queue: [], // uploads to do
        active_upload_queue: [], // currently running uploads
        overwriteall: false, // Flag: overwrite all the files in this queue
        skipall: false, // Flag: skip all already existing files

        /**
         * Reset internal values.
         * 
         * Don't remove the dialog
         */
        reset : function(){
			this.is_working = false;
			// this.has_errors = false;
            this.upload_queue = [];
            this.overwriteall = false;
            this.skipall = false;
            this.active_upload_queue = [];
		},
        
        hideUploadDialog : function() {
            if (Skylable_Uploads.hasUploadDialog()) {
                Skylable_Uploads.dlg.dialog('close');
            }
        },

        showUploadDialog : function() {
            if (Skylable_Uploads.hasUploadDialog()) {
                Skylable_Uploads.dlg.dialog('open');
            }
        },

        /**
         * Tells if the upload dialog is enabled.
         * 
         * @returns {boolean}
         */
        hasUploadDialog : function() {
            if (Skylable_Uploads.dlg !== null) {
                return (Skylable_Uploads.dlg.dialog('instance') !== undefined);    
            } 
            return false;
        },

        /**
         * Delete the upload dialog
         */
        removeUploadDialog : function() {
            if (Skylable_Uploads.hasUploadDialog()) {
                Skylable_Uploads.dlg.dialog('close');
                Skylable_Uploads.dlg.dialog('destroy');
            }
            Skylable_Uploads.has_errors = false;
            Skylable_Uploads.dlg = null;
        },

        /**
         * Checks the upload queue: if the queue is empty, reset some internal variables
         * and returns true.
         * Otherwise don't set anything and returns false.
         * @returns {boolean}
         */
        checkUploadQueue : function() {
            if (Skylable_Uploads.upload_queue.length == 0) {
                Skylable_Uploads.overwriteall = false;
                Skylable_Uploads.skipall = false;

                return true;
            }
            return false;
        },

        /**
         * Update the upload dialog buttons when there are no more uploads in queue
         */
        noMoreUploadsDialogButtons : function(){
            if (Skylable_Uploads.hasUploadDialog()) {
                Skylable_Uploads.dlg.dialog('option', 'buttons', [
                    {
                        text : Skylable_Lang.closeBtn,
                        click : function(e) {
                            Skylable_Uploads.removeUploadDialog();
                        }
                    }
                ]);    
            } else {
                console.log('nomoreUploadDialogButtons: No dialog');   
            }
            
        },
        

        /**
         * Uploads the file in the upload queue.
         * 
         * To avoid async AJAX calls advance the queue in a recursive way or 
         * using the 'always' event handler of the upload handler.
         */
        startUploading : function() {
            console.log('start uploading...');
            
            /**
             * There are no more uploads: update the file list.
             * 
             */
            if (Skylable_Uploads.checkUploadQueue() ) {
                Skylable_Uploads.noMoreUploadsDialogButtons();
                
                Skylable_Uploads.updateFilelist(true);
            }
            
            if (Skylable_Uploads.dlg === null) {
                Skylable_Uploads.is_working = true;
                Skylable_Uploads.has_errors = false;
                Skylable_Uploads.prepareUploadDialog(true);
            }
            
            var file = Skylable_Uploads.upload_queue.pop();
            if (file) {

                // Overwrite mode on: 
                if (Skylable_Uploads.overwriteall) {
                    
                    file.submit();
                    
                    Skylable_Uploads.startUploading();
                } else {

                    var the_file = '';
                    if(typeof file.relativePath !== 'undefined') {
                        the_file = file.relativePath + file.files[0].name;
                    } else {
                        the_file = file.files[0].name;
                    }
                    var file_path = current_path + the_file;

                    // Check if the file exists, asks what to do
                    $('#overwrite_confirmation_dlg').remove();
                    $.ajax({
                        url: "/fileexists",
                        data: 'path=' + encodeURIComponent(file_path),
                        dataType: "json",
                        async : true,
                        success : function(qdata, status, xhr) {
                            if (qdata.status == false) {
                                file.submit();

                                // Skylable_Uploads.startUploading();
                            } else {
                                
                                // Skip all already existing files...
                                if (Skylable_Uploads.skipall) {
                                    Skylable_Uploads.startUploading();
                                } else {
                                    // File exists, should we overwrite it?
                                    var dlg_no_means_no = true; // FLAG: if true on closing, use the default "no" action
                                    var dlg = $('<div id="overwrite_confirmation_dlg"><p>'+
                                    Skylable_Utils.nl2br(sprintf(Skylable_Lang.uploadFileAlreadyExistsOverwrite, the_file)) +
                                    '</p></div>').hide().appendTo('body');
                                    
                                    dlg.dialog({
                                        autoOpen: false,
                                        modal : true,
                                        resizable: true,
                                        width : Skylable_Utils.defaultDialogWidth(),
                                        title: Skylable_Lang.uploadTitle,
                                        close : function(ev, ui) {
                                            if (dlg_no_means_no) {
                                                $(this).dialog('destroy');
                                                Skylable_Uploads.showUploadDialog();

                                                Skylable_Uploads.startUploading();
                                            }
                                        },
                                        buttons :[{
                                            text: Skylable_Lang.yesBtn,
                                            click : function() {
                                                dlg_no_means_no = false;
                                                $(this).dialog('close');
                                                $(this).dialog('destroy');
                                                Skylable_Uploads.showUploadDialog();

                                                file.submit();

                                                // Skylable_Uploads.startUploading();
                                            }
                                        },{
                                            text: Skylable_Lang.noBtn,
                                            click : function() {
                                                dlg_no_means_no = false;
                                                $(this).dialog('close');
                                                $(this).dialog('destroy');
                                                Skylable_Uploads.showUploadDialog();

                                                Skylable_Uploads.startUploading();
                                            }
                                        },{
                                            text: Skylable_Lang.cancelBtn,
                                            click : function() {
                                                dlg_no_means_no = false;
                                                $(this).dialog('close');
                                                $(this).dialog('destroy');
                                                Skylable_Uploads.removeUploadDialog();
                                                Skylable_Uploads.cancelUploads();
                                            }
                                        },
                                            {
                                                text: Skylable_Lang.uploadOverwriteAll,
                                                click : function() {
                                                    dlg_no_means_no = false;
                                                    $(this).dialog('close');
                                                    $(this).dialog('destroy');
                                                    Skylable_Uploads.showUploadDialog();

                                                    Skylable_Uploads.overwriteall = true;

                                                    file.submit();

                                                    // Skylable_Uploads.startUploading();

                                                }
                                            },
                                            {
                                                text: Skylable_Lang.uploadSkipAll,
                                                click : function() {
                                                    dlg_no_means_no = false;
                                                    $(this).dialog('close');
                                                    $(this).dialog('destroy');
                                                    Skylable_Uploads.showUploadDialog();

                                                    Skylable_Uploads.skipall = true;

                                                    Skylable_Uploads.startUploading();

                                                }
                                            }
                                        ]
                                    });
                                    
                                    Skylable_Uploads.hideUploadDialog();
                                    dlg.dialog('open');    
                                }
                                
                                
                            }
                        },
                        error : function(xhr, status) {

                            file.textStatus = 'fileexistsfail';

                            if (xhr.getResponseHeader('Content-Type') === 'application/json') {
                                var response_text = JSON.parse(xhr.responseText);
                                file.errorThrown = response_text.error;
                            } else {
                                file.errorThrown = xhr.responseText;
                            }
                        },
                        complete : function(xhr, status) {

                            
                        }

                    });
                }

            } else {
                Skylable_Uploads.overwriteall = false;
            }
        },
        
        /**
         * Tells if there are uploads in progress.
         * @returns {boolean}
         */
        isUploading : function() {
            var count = 0;
                        
            jQuery.each(Skylable_Uploads.active_upload_queue, function(i, data) {
                if (data.state() === 'pending') {
                    count++;
                }
            });
            return count > 0;
        },

        /**
         * Cancel all active uploads and clean up the upload queue.
         */
        cancelUploads : function() {
            try {
                for (var i=0; i < Skylable_Uploads.active_upload_queue.length; i++) {
                    Skylable_Uploads.active_upload_queue[i].abort();
                    delete Skylable_Uploads.upload_queue[i].jqXHR;
                }
            } catch(err) {

            }

            Skylable_Uploads.upload_queue = [];
            Skylable_Uploads.active_upload_queue = [];
            
            Skylable_Uploads.reset();
        },

        /**
         * Update the file list and close the upload window (if any)
         */
        updateFilelist : function(close_window) {
            FileOperations.updateFileList(function(s){
                Skylable_Uploads.is_working = false;

                if (!Skylable_Uploads.has_errors && Skylable_Uploads.hasUploadDialog()) {
                    if (close_window) {
                        Skylable_Uploads.removeUploadDialog();
                    }
                }
            });
        },

        /**
         * Initialize the upload dialog.
         * 
         * @param boolean show_dlg true after initialization, show the dialog
         */
        prepareUploadDialog : function(show_dlg) {
            Skylable_Uploads.dlg = $('#dialog');

            Skylable_Uploads.dlg.dialog({
                autoOpen: false,
                modal: true,
                resizable: true,
                title: Skylable_Lang.uploadTitle,
                width : Skylable_Utils.defaultDialogWidth(),
                closeOnEscape : false,
                create : function() {
                    // disable the X for closing the window
                    $(this).closest('div.ui-dialog')
                        .find('.ui-dialog-titlebar-close')
                        .hide();
                        /*
                        .click(function(e) {
                            console.log('disabled...');
                            e.preventDefault();
                        });
                        */
                }
            });

            Skylable_Uploads.dlg.html('<div id="progressbar"><div class="progress-label"></div></div><div id="files"></div>');
            $('#progressbar').progressbar({
                max : 100,
                value : 1,
                enable: true
            });

            Skylable_Uploads.dlg.dialog('option', 'buttons', [
                {
                    text : Skylable_Lang.cancelBtn,
                    click : function(e) {
                        Skylable_Uploads.cancelUploads();

                        Skylable_Uploads.noMoreUploadsDialogButtons();
                        Skylable_Uploads.updateFilelist(true);
                        
                    }
                }
            ]);

            if (show_dlg) {
                Skylable_Uploads.dlg.dialog('open');
            }
        }
	};
}

$(document).ready(function(){

	$("#addfile, #addfile_mobile").click(function(){
		$('#fileupload').click();
	});

    // If there are uploads you can't leave the page
    $(window).on('beforeunload', function(e) {
        if (Skylable_Uploads.isUploading()) {
            return Skylable_Lang.uploadBrowserWindowCloseConfirm;
        }
    });

    $('#fileupload').fileupload({
		url: upload_url,
		dataType: 'json',
        autoUpload: false,
        sequentialUpload: true,
        singleFileUploads : true,
        
        add : function (e, data) {

            /**
             * We get an upload file list, but only one file at a time
             * so we build an upload queue.
             */
            
            /**
             * The first file into the file list is special, we attach
             * the upload queue to it.
            * */
            if ( ! data.originalFiles.sx_upload_queue) {
                data.originalFiles.sx_upload_queue = {
                    uploads : [],
                    upload_count : data.originalFiles.length, // the number of files to process
                    uploads_accepted : 0 // counts the file processed
                }
            }
            var sx_upload_queue = data.originalFiles.sx_upload_queue;
            var file = data.files[0];

            // Taken from owncloud source code.
            // in case folder drag and drop is not supported file will point to a directory
            // http://stackoverflow.com/a/20448357
            if ( ! file.type && file.size%4096 === 0 && file.size <= 102400) {
                try {
                    var reader = new FileReader();
                    reader.readAsBinaryString(file);
                } catch (NS_ERROR_FILE_ACCESS_DENIED) {
                    //file is a directory
                    data.textStatus = 'dirorzero';
                    data.errorThrown = sprintf( Skylable_Lang.uploadDirError, file.name);
                }
            }
            
            // The file is too big
            if (file.size > maxFileSize) {
                data.textStatus = 'sizeexceedlimit';
                data.errorThrown = Skylable_Utils.nl2br(sprintf(Skylable_Lang.uploadExceedingFileSize, file.name, file.size, maxFileSize));
            } else {

                sx_upload_queue.uploads.push(data);
                sx_upload_queue.uploads_accepted++;

                /**
                 * If all uploads are accepted, start the uploading from the upload queue
                 */
                if ((sx_upload_queue.uploads_accepted >= sx_upload_queue.upload_count) && !data.errorThrown) {
                    // remove the temporary queue
                    delete data.originalFiles.sx_upload_queue;
                    Skylable_Uploads.upload_queue = sx_upload_queue.uploads;

                    Skylable_Uploads.startUploading();
                }
            } 

            // Stops on errors
            var _this = $(this);
            if (data.errorThrown) {
                var fu = _this.data('blueimp-fileupload') || _this.data('fileupload');
                fu._trigger('fail', e, data);
                return false; 
            }
            
            return true;
        },

        submit : function (e, data) {
            // If uploading a directory, tries to preserve directory structure
            if ( ! data.formData ) {
                var fileDirectory = '';
                if(typeof data.files[0].relativePath !== 'undefined') {
                    fileDirectory = data.files[0].relativePath;
                }
                data.formData = {
                    file_directory: fileDirectory
                };
            }

            Skylable_Uploads.active_upload_queue.push(data);
            
        },
		start : function(e) {
            console.log('START');
		},
		progressall: function (e, data) {
			var progress = parseInt(data.loaded / data.total * 100, 10);
			$('#progressbar').progressbar('option', 'value', progress);
			$('#progressbar .progress-label').text(progress + '%');
		},
		done: function(e, data) {
            console.log('DONE.');
			/*
            $('#progressbar').progressbar('value', 100);
			$('#progressbar .progress-label').text('100%');
            */
            
			var reply_box = $('#dialog #files');

			if (data.textStatus === 'success') {
				for(var idx in data.result.files) {
					var the_file = data.result.files[idx];
					if (the_file.error) {
						Skylable_Uploads.has_errors = true;
                        reply_box.append('<p class="upload_error">' + sprintf(Skylable_Lang.uploadFailed, Skylable_Utils.trs(the_file.old_name, 55), the_file.error) + '</p>');
					} else {
                        reply_box.append('<p class="upload_success">'+ sprintf(Skylable_Lang.uploadSuccess, Skylable_Utils.trs(the_file.old_name, 55)) + '</p>');
					}
				}
			} else {
				Skylable_Uploads.has_errors = true;
                reply_box.append('<p class="upload_error">' + sprintf(Skylable_Lang.uploadError , data.errorThrown ) + '</p>');
			}

            
		},
		fail: function(e, data) {
            console.log('FAIL HANDLER');
            console.log(data);

            if (typeof data.textStatus !== 'undefined' && data.textStatus !== 'success' ) {

                var reply_box = $('#dialog #files');
                var err_msg = data.errorThrown;
                
                if (data.textStatus === 'abort') {
                    err_msg = Skylable_Lang.uploadCanceled;
                } else {
                    if (data.jqXHR) {
                        if (data.jqXHR.status == 500) {
                            if (data.jqXHR.responseJSON.files) {
                                err_msg = data.jqXHR.responseJSON.files[0].error;
                            } else if (data.jqXHR.responseJSON.error) { // Internal error
                                err_msg = data.jqXHR.responseJSON.error;
                            }
                        }
                    }
                }
                
                Skylable_Uploads.has_errors = true;
                if (err_msg.length == 0) {
                    err_msg = Skylable_Lang.uploadAborted;
                }

                console.log('Error: ' + err_msg);

                // Shows an error dialog if needed
                if (reply_box.length == 0) {
                    Skylable_Uploads.dlg = $('#dialog');

                    Skylable_Uploads.dlg.dialog({
                        autoOpen: false,
                        modal: true,
                        resizable: true,
                        title: Skylable_Lang.uploadTitle,
                        beforeClose: function(ev, ui) {
                            Skylable_Uploads.cancelUploads();
                            return true;
                        }
                    });

                    Skylable_Uploads.dlg.html('<div id="files"></div>');

                    Skylable_Uploads.dlg.dialog('option', 'buttons', [
                        {
                            text : Skylable_Lang.closeBtn,
                            click : function(e) {
                                $(reply_box).remove();
                                Skylable_Uploads.removeUploadDialog();
                            }
                        }
                    ]);

                    Skylable_Uploads.dlg.dialog('open');
                    reply_box = $('#dialog #files');
                } 
                
                reply_box.append('<p class="upload_error">' + sprintf(Skylable_Lang.uploadError , err_msg ) + '</p>');    
            }

		},
        always : function (e, data) {
            console.log('ALWAYS');
            
            if (!Skylable_Uploads.checkUploadQueue()) {
                // Advance the upload queue...
                Skylable_Uploads.startUploading();
            } else {
                // The upload queue is empty: show the right dialog and update the file list
                Skylable_Uploads.noMoreUploadsDialogButtons();
                Skylable_Uploads.updateFilelist(true);
            }
            
        }
	});
});
