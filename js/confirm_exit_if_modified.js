/**
 * adapted from http://stackoverflow.com/questions/155739/detecting-unsaved-changes
 */

/**
 * Determines if a form is dirty by comparing the current value of each element
 * with its default value.
 *
 * @param {Form} form the form to be checked.
 * @return {Boolean} <code>true</code> if the form is dirty, <code>false</code>
 *                   otherwise.
 */
function formIsDirty(form) {
    for (var i = 0; i < form.elements.length; i++) {
        var element = form.elements[i];
        var type = element.type;
        if (type == "checkbox" || type == "radio") {
            if (element.checked != element.defaultChecked) {
                return true;
            }
        } else if (type == "text" || type == "textarea") {
            if (element.value != element.defaultValue) {
                return true;
            }
        }
    }
    return false;
}

function confirmExitIfModified(form, message) {
    window.onbeforeunload = function (e) {
        e = e || window.event;
        if (formIsDirty(document.forms[form])) {
            // For IE and Firefox
            if (e) {
                e.returnValue = message;
            }
            // For Safari
            return message;
        }
    }
}
