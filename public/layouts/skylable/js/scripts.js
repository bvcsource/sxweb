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


$(document).ready(function() {
    
    $( "#sidebar-trigger" ).click(function() {

        $('#sidebar .inner-scroll-wrap').slimScroll({ destroy: true });
        
        $(this).toggleClass(function () {
            if ($(this).is(".pressed")) {
                $(this).removeClass("pressed");
                $.cookie('bar', "", {expires: 7, path: '/'});
                return "";
            } else {
                $.cookie('bar', 'pressed', {expires: 7, path: '/'});
                return "pressed";
            }
        });
        
        $("#sidebar").toggleClass("sidebar-opened", 200, 'easeInOutExpo', function(){
            $(".drag-drop-wrap").toggleClass("sidebar-margin", 200, 'easeInOutExpo', addSlimScroll );    
        });

        recalcContentHeight();
       
	});

    $( ".sidebar ul li a" ).hover(function() {
        $( ".inner-scroll-wrap" ).toggleClass( "spread" );
        $( ".sidebar .slimScrollDiv" ).toggleClass( "spread" );
    });
    
    $(".icon-failure, .icon-success").click(function() {
        $(this).parent().parent().remove();
    });

    var cf = $.cookie('bar');
    if(cf=="pressed" && !$("#sidebar-trigger").is(".pressed")) $( "#sidebar-trigger").click();


    addSlimScroll();
    
    // Add the "Select All" file list behavior
    $("p.table-title span.date").append('<input style="float: right;" type="checkbox" name="table_title_file_list_select_all">').click(function(e){
        var is_checked = $('p.table-title span.date input[name=table_title_file_list_select_all]').prop('checked');
        $('#main-file-list input[name=file_element]').prop('checked', is_checked );
    });
    
    recalcContentHeight();


    $(window).on("resize.window", function(){
        $('#sidebar .inner-scroll-wrap').slimScroll({ destroy: true });
        addSlimScroll();

        recalcContentHeight();
    });
    
});

function addSlimScroll() {

    /*
    var d1 = $('#sidebar .volumes');
    var d1_off = d1.offset().top + d1.outerHeight(true);
    */
    var d1 = $('#sidebar .inner-scroll-wrap');
    var d1_off = d1.offset().top;
    
    var d2 = $('#sidebar .sidebar-tools');
    var d2_off = d2.offset().top;

    var h = parseInt(d2_off - d1_off, 10);
    
    $('#sidebar .inner-scroll-wrap').slimScroll({
        size: '10px',
        position: 'right',
        color: '#5b6277',
        height: h + 'px',
        alwaysVisible: true,
        railVisible: false,
        railColor: '#222',
        allowPageScroll: false,
        disableFadeOut: true
    });


    recalcContentHeight();
}

/**
 * Calculate the content height
 */
function recalcContentHeight() {
    var sticked = $('.drag-drop-wrap .sticked');
    if ($(sticked).is(':visible')) {
        $('#selectable').css('margin-top', $(sticked).outerHeight());
    } 
}

