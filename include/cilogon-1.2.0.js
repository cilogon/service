/* CIL-2213 - If you make any changes to this file, you must rename the file
 * to increment the version number due to the Apache HTTP server being
 * configured with a long cache lifetime (1 year). Changing the file name
 * forces a client browser to load any updates. You must also update the
 * printFooter() function in service-lib/src/Service/Content.php to reference
 * the renamed file.
 */

/***************************************************************************
 * Function  : addLoadEvent                                                *
 * Parameters: The function to be called upon window loading               *
 * Rather than use the <body onload="myfunc"> tag to invoke some piece of  *
 * JavaScript upon page loading, call addLoadEvent(myfunc).  This function *
 * allows for multiple functions to be called when the page is loaded. If  *
 * there is already an onload function defined, it appends the new         *
 * function to the onload event handler.                                   *
 ***************************************************************************/
function addLoadEvent(func)
{
    var oldonload = null;
    if ('onload' in window) {
        oldonload = window.onload;
    }
    if (typeof oldonload !== 'function') {
        window.onload = func;
    } else {
        window.onload = function () {
            if (oldonload) {
                oldonload();
            }
            func();
        };
    }
}

/***************************************************************************
 * Function  : enterKeySubmit                                              *
 * This function catches when the <Enter> key is pressed on the bootstrap- *
 * select element and clicks the "Log On" button.                          *
 ***************************************************************************/
function enterKeySubmit()
{
    var logonbutton = document.getElementById("wayflogonbutton");
    var elems = document.getElementsByClassName("dropdown bootstrap-select");
    if ((logonbutton !== null) && (elems !== null)) {
        document.addEventListener("keyup", function onPress(event)
        {
            if (event.key === "Enter") {
                if (
                    document.hasFocus() &&
                    document.activeElement !== null &&
                    document.activeElement.parentNode !== null &&
                    elems.length > 0 &&
                    elems[0].firstChild !== null &&
                    elems[0].firstChild.parentNode !== null &&
                    document.activeElement.parentNode === (elems[0].firstChild.parentNode)
                   ) {
                    logonbutton.click();
                }
            }
        });
    }
}

/***************************************************************************
 * Function  : doFocus                                                     *
 * Parameter : The id of an input element to focus on.                     *
 * Returns:  : True if text input element got focus, false otherwise.      *
 * This function is a helper function called by focusOnElement.  It        *
 * attempts to find the passed-in text input element by id.  If found, it  *
 * tries to set focus.  If successful, it returns true.  If any step       *
 * fails, it returns false.                                                *
 ***************************************************************************/
function doFocus(id)
{
    var success = false;
    var elem = document.getElementById(id);
    if (elem !== null) {
        // Ignore hidden elements due to Bootstrap 'collapse'
        var box = elem.getBoundingClientRect();
        if ((box !== null) && (box.width > 0) && (box.height > 0)) {
            try {
                elem.focus();
                success = true;
            } catch (e) {
            }
        }
    }
    return success;
}

/***************************************************************************
 * Function  : focusBootstrapSelect                                        *
 * Returns:  : True if bootstrap-select element got focus, else false      *
 * This function is a helper function called by focusOnElement.  It        *
 * attempts to find the bootstrap-select element by class name. If found,  *
 * tries to set focus.  If successful, it returns true.  If any step       *
 * fails, it returns false.                                                *
 ***************************************************************************/
function focusBootstrapSelect()
{
    var success = false;
    var elems = document.getElementsByClassName("dropdown bootstrap-select");
    if ((elems !== null) && (elems.length > 0)) {
        try {
            (elems[0]).firstChild.focus();
            success = true;
        } catch (e) {
        }
    }
    return success;
}

/***************************************************************************
 * Function  : focusOnElement                                              *
 * This function looks for one of several text fields on the current page  *
 * and attempts to give text field focuts to each field, in order.         *
 ***************************************************************************/
function focusOnElement()
{
    return doFocus("user-code") ||
           focusBootstrapSelect();
}

/***************************************************************************
 * Function  : validateForm                                                *
 * For form elements that are marked as needing validation, add            *
 * event listenters for 'submit' to check validity of input data.          *
 ***************************************************************************/
function validateForm()
{
    var forms = document.getElementsByClassName('needs-validation');
    var validation = Array.prototype.filter.call(forms, function (form) {
        form.addEventListener('submit', function (event) {
            if (form.checkValidity() === false) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}

/***************************************************************************
 * Function  : upperCaseF                                                  *
 * Transform the input field to all uppercase characters.                  *
 ***************************************************************************/
function upperCaseF(a)
{
    a.value = a.value.toUpperCase();
    a.value = a.value.replace(/@.*/, "");
}

/***************************************************************************
 * Function  : updateIdPList                                               *
 * Perform an asynchronous 'GET' of the 'idplist' endpoint (respecting     *
 * any skin and idphint query parameters that may have been specified)     *
 * and dynamically populate list of Identity providers. The initial HTML   *
 * will have just a single IdP in the selection list. This function adds   *
 * the rest of the IdPs as <option> elements and then refreshes the        *
 * bootstrap-select element.                                               *
 * NOTE: This method requires jQuery to be loaded beforehand.              *
 ***************************************************************************/
function updateIdPList()
{
    var providerId = document.getElementById('providerId');
    if (providerId !== null) {
        var query = {};
        // If skin parameter, 'skinname' is stored in a hidden <input> element
        var skinid = document.getElementById('skinname');
        if (skinid !== null) {
            query.vo = skinid.value;
        }
        // If idphint parameter, get hidden <input> element 'idphintlist'
        var idphintid = document.getElementById('idphintlist');
        if (idphintid !== null) {
            query.idphint = idphintid.value;
        }
        // If showhidden parameter, get hidden <input> element 'showhidden'
        var showhiddenid = document.getElementById('showhidden');
        if (showhiddenid !== null) {
            query.showhidden = showhiddenid.value;
        }
        // Perform async 'GET' of the idplist endpoint (with skin/idphint)
        $.ajax({
            url: '/idplist/',
            data: query,
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                // Loop through the IdPs, adding new <option> elements,
                // skipping the ones already added to the list.
                var sel = [];
                $.each(providerId.options, function(i, opt) {
                    sel[i] = $(opt).text();
                });
                $.each(data, function (index, value) {
                    if (sel.indexOf(value.DisplayName) === -1) {
                        $('.selectpicker').append(
                            '<option data-tokens="' + value.EntityID +
                            '" value="' + value.EntityID + '"' +
                            ((value.Hidden === true) ? ' hidden="hidden"' : '') +
                            '>' + value.DisplayName + '</option>'
                        );
                    }
                });
                $('.selectpicker').selectpicker('refresh');
            }
        });
    }
}

/***************************************************************************
 * Function  : statusEmbed                                                 *
 * Embed a StatusPage.io popup frame to show scheduled maintenance and     *
 * unplanned outages. Load this function AFTER updateIdPList().            *
 * Source: https://manage.statuspage.io/pages/mj968hlbbyn6/status-embed    *
 ***************************************************************************/
function statusEmbed()
{
    var frame = document.createElement('iframe');
    var mobile = (screen.width < 450);
    var actions = {
        dismissFrame: function() {
            frame.style.left = '-9999px';
        },
        showFrame: function() {
            if (mobile) {
                frame.style.left = '0';
                frame.style.bottom = '0';
            } else {
                frame.style.left = '60px';
                frame.style.right = 'auto';
            }
        }
    };

    frame.src = 'https://mj968hlbbyn6.statuspage.io/embed/frame';
    frame.title = 'CILogon Status';
    frame.style.position = 'fixed';
    frame.style.border = 'none';
    frame.style.boxShadow = '0 20px 32px -8px rgba(9,20,66,0.25)';
    frame.style.zIndex = '9999';
    frame.style.transition = 'left 1s ease, bottom 1s ease, right 1s ease';

    if (mobile) {
        frame.src += '?mobile=true';
        frame.style.height = '20vh';
        frame.style.width = '100vw';
        frame.style.left = '-9999px';
        frame.style.bottom = '-9999px';
        frame.style.transition = 'bottom 1s ease';
    } else {
        frame.style.height = '115px';
        frame.style.width = '320px';
        frame.style.left = '-9999px';
        frame.style.right = 'auto';
        frame.style.bottom = '60px';
    }

    document.body.appendChild(frame);

    window.addEventListener('message', function(event) {
        if (event.data.action && actions.hasOwnProperty(event.data.action)) {
            actions[event.data.action](event.data);
        }
    }, false);

    window.statusEmbedTest = actions.showFrame;
}

/***************************************************************************
 * Function  : setLang                                                     *
 * When multi-language support is configured for a skin, a dropdown menu   *
 * is shown to the user. When the user selects one of the language         *
 * options, set a "lang" cookie and reload the page.                       *
 ***************************************************************************/
function setLang(lang)
{
    var expires = (new Date(Date.now() + 31536000)).toUTCString();
    document.cookie = 'lang=' + lang + '; expires=' + expires +
                      '; path=/; secure; samesite=lax';
    window.location = window.location; // Reload without POST
}

addLoadEvent(updateIdPList);
addLoadEvent(focusOnElement);
addLoadEvent(enterKeySubmit);
addLoadEvent(validateForm);
addLoadEvent(statusEmbed);
