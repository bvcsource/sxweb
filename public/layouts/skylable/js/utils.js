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

if (!Skylable_Utils) {
    /**
     * Various utility functions.
     * 
     * @type {{removeRootFromPath: Function, getRootFromPath: Function, slashPath: Function, basename: Function}}
     */
    var Skylable_Utils = {
        /**
         * Remove the first part of a path.
         * @param path
         * @returns {string}
         */
        removeRootFromPath : function(path) {
            if (path.length == 0) {
                return '/';
            }

            var p = path.indexOf('/', path.indexOf('/') + 1);
            if (p < 0) {
                return '/';
            } else {
                return path.substring(p);
            }
        },

        /**
         * Returns the root from a path
         * @param path
         * @returns {string}
         */
        getRootFromPath : function(path) {
            if (path.length == 0) {
                return '';
            }

            var p1 = path.indexOf('/');
            if (p1 < 0) {
                return path;
            } else if (p1 > 0) {
                return path.substring(0, p1);
            } else {
                var p2 = path.indexOf('/', p1 + 1);
                if (p2 < 0) {
                    return path.substring(p1 + 1);
                } else {
                    return path.substring(p1 + 1, p2);
                }
            }
        },

        /**
         * Add a slash to the beginning part of a path
         * @param {string} path
         * @returns {string}
         */
        slashPath : function(path) {
            if (path.length == 0) {
                return '/';
            }
            var p1 = path.indexOf('/');
            if (p1 > 0) {
                return '/' + path;
            }
            return path;
        },

        /**
         * Returns the last part of a path
         * @param path
         * @returns {string}
         */
        basename : function(path) {
            if (path.length == 0) {
                return '';
            }
            var p = path.lastIndexOf('/');
            if (p == path.length - 1) {
                p--;
                p = path.lastIndexOf('/', p);
            }
            if (p != -1) {
                return path.substring(++p, path.length);
            }
            return path;
        },

        /**
         * Trim a string to a given length and adds trailing '...'. 
         * @param str
         * @param length
         * @returns {string}
         */
        trs : function (str, length) {
            return (str.length > (length - 3) ? str.substring(0, length - 3) + '...' : str);
        },

        /**
         * Convert NL chars to HTML break 
         * @param str
         * @returns {XML|string|void|*}
         */
        nl2br : function (str) {
            return str.replace(/(?:\r\n|\r|\n)/g, '<br />');
        }
    }
}
