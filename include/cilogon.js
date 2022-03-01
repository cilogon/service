/***************************************************************************
 * Function  : partial                                                     *
 * Parameters: Any parameters to be passed to the partial function.        *
 * Returns   : A "partial" function which can be passed as an argument     *
 *             to another JS function.                                     *
 * This function was taken from :                                          *
 * http://stackoverflow.com/questions/373157/how-can-i-pass-a-reference-to-a-function-with-parameters
 * It creates a "partial" function, which is basically a function with a   *
 * bunch of parameters preset.  This new partial function can be passed    *
 * to other JavaScript functions which expect to receive a function name   *
 * (withOUT parameters) as a parameter (e.g. countdown(f,ms) and           *
 * addLoadEvent(f).                                                        *
 ***************************************************************************/
function partial(func) /* func is 0..n args */
{
    var i;
    var args = [];
    for (i = 1; i < arguments.length; i = i + 1) {
        args.push(arguments[i]);
    }
    return function () {
        var allArguments = args.concat(Array.prototype.slice.call(arguments));
        return func.apply(this, allArguments);
    };
}

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
 * Function  : countdown                                                   *
 * Parameters: (1) Prefix to prepend to "expire" and "value" ids.          *
 *             (2) Label to prepend to "Expires:" time.                    *
 * This function counts down a timer for a paragraph with an attribute     *
 * id=which+"expire".  In this case "which" can be "p12".                  *
 * If there is still time left in the expire element, the value is fetched *
 * and decremented by one second, then updated.  Once time has run out,    *
 * the which+"value" and which+"expire" paragraph elements are set to      *
 * empty strings, which hides them.                                        *
 ***************************************************************************/
function countdown(which, expirelabel)
{
    var expire = document.getElementById(which + "expire");
    if (expire !== null) {
        var expiretext = expire.innerHTML;
        if ((expiretext !== null) && (expiretext.length > 0)) {
            var matches = expiretext.match(/\d+/g);
            if ((matches !== null) && (matches.length === 2)) {
                var minutes = parseInt(matches[0], 10);
                var seconds = parseInt(matches[1], 10);
                if ((minutes > 0) || (seconds > 0)) {
                    seconds -= 1;
                    if (seconds < 0) {
                        minutes -= 1;
                        if (minutes >= 0) {
                            seconds = 59;
                        }
                    }
                    if ((seconds > 0) || (minutes > 0)) {
                        expire.innerHTML = expirelabel + " Expires: " +
                          ((minutes < 10) ? "0" : "") + minutes + "m:" +
                          ((seconds < 10) ? "0" : "") + seconds + "s";
                        var pc = partial(countdown, which, expirelabel);
                        setTimeout(pc, 1000);
                    } else {
                        expire.innerHTML = "";
                        var thevalue = document.getElementById(which + "value");
                        if (thevalue !== null) {
                            thevalue.innerHTML = "";
                        }
                    }
                }
            }
        }
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
    return doFocus("password1") ||
           doFocus("heading-gencert") ||
           doFocus("lifetime") ||
           doFocus("user-code") ||
           focusBootstrapSelect();
}

/***************************************************************************
 * Function  : checkPassword                                               *
 * This function is called onkeyup when the user types in one of the two   *
 * passwords on the main page.  It verifies that the first password is     *
 * at least 12 characters long, and that the second password matches.  It  *
 * changes the password icons next to the input text fields as appropriate.*
 ***************************************************************************/
function checkPassword()
{
    var pw1input = document.getElementById("password1");
    var pw2input = document.getElementById("password2");
    var pw1icon = document.getElementById("pw1icon");
    var pw2icon = document.getElementById("pw2icon");
    var pw1text;
    var pw2text;
    if ((pw1input !== null) && (pw2input !== null) &&
        (pw1icon !== null) && (pw2icon !== null)) {
        pw1text = pw1input.value;
        pw2text = pw2input.value;
        if ((pw1text.length === 0) && (pw2text.length === 0)) {
            pw1icon.className = "fa fa-fw";
            pw2icon.className = "fa fa-fw";
        } else if (pw1text.length < 12) {
            pw1icon.className = "fa fa-fw fa-exclamation-circle";
            pw2icon.className = "fa fa-fw";
        } else if (pw1text !== pw2text) {
            pw1icon.className = "fa fa-fw fa-check-square";
            pw2icon.className = "fa fa-fw fa-exclamation-circle";
        } else {
            pw1icon.className = "fa fa-fw fa-check-square";
            pw2icon.className = "fa fa-fw fa-check-square";
        }
    }
}

/***************************************************************************
 * Function  : showHourglass                                               *
 * Parameter : Which hourglass icon to show (e.g. 'p12')                   *
 * This function is called when the "Get New Certificate" button is        *
 * clicked.  It unhides the small hourglass icon next to the button.       *
 * The "which" parameter corresponds to the prefix of the                  *
 * id=which+"hourglass" attribute of the <img>.                            *
 ***************************************************************************/
function showHourglass(which)
{
    var thehourglass = document.getElementById(which + 'hourglass');
    if (thehourglass !== null) {
        var pw1 = document.getElementById('password1');
        var pw2 = document.getElementById('password2');
        if ((pw1 !== null) &&
            (pw2 !== null) &&
            (pw1.value.length >= 12) &&
            (pw2.value.length >= 12)
           ) {
            thehourglass.style.display = 'inline';
        }
    }
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
        // If skin parameter, 'skinname' is stored in a hidden <input> element
        var skinid = document.getElementById('skinname');
        var skinname = '';
        if (skinid !== null) {
            skinname = skinid.value;
        }
        // If idphint parameter, get hidden <input> element 'idphintlist'
        var idphintid = document.getElementById('idphintlist');
        var idphintlist = '';
        if (idphintid !== null) {
            idphintlist = idphintid.value;
        }
        // Perform async 'GET' of the idplist endpoint (with skin/idphint)
        $.ajax({
            url: '/idplist/' +
                (skinname.length > 0 || idphintlist.length > 0 ? '?' : '') +
                (skinname.length > 0 ? 'vo=' + skinname : '') +
                (skinname.length > 0 && idphintlist.length > 0 ? '&' : '') +
                (idphintlist.length > 0 ? 'idphint=' + idphintlist : ''),
            type: 'GET',
            dataType: 'json',
            success: function (data) {
                // Loop through the IdPs, adding new <option> elements,
                // skipping the first one already selected in the list.
                var sel = providerId.options[0].text;
                $.each(data, function (index, value) {
                    if (value.DisplayName !== sel) {
                        $('.selectpicker').append(
                            '<option data-tokens="' + value.EntityID +
                            '" value="' + value.EntityID +
                            '">' + value.DisplayName + '</option>'
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

var fp12 = partial(countdown, 'p12', 'Link');
addLoadEvent(fp12);
addLoadEvent(updateIdPList);
addLoadEvent(focusOnElement);
addLoadEvent(enterKeySubmit);
addLoadEvent(validateForm);
addLoadEvent(statusEmbed);
