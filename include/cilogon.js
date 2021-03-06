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

var fp12 = partial(countdown, 'p12', 'Link');
addLoadEvent(fp12);
addLoadEvent(focusOnElement);
addLoadEvent(enterKeySubmit);
addLoadEvent(validateForm);
