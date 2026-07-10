/**
 * RateSection JavaScript functions
 * Used by quickbudget_config.php for rate editing
 */

function setRateFromSelect_js(type, value, existingRates) {
    var rateInput = document.getElementById(type + '_rate');
    if (existingRates[value]) {
        rateInput.value = existingRates[value];
        document.getElementById(type + '_is_edit').value = '1';
        document.getElementById(type + '_submit').value = _('Update Rate');
    } else {
        rateInput.value = '';
        document.getElementById(type + '_is_edit').value = '0';
        document.getElementById(type + '_submit').value = _('Save Rate');
    }
}

function editRate_js(type, ref, rate) {
    document.getElementById(type + '_ref').value = ref;
    document.getElementById(type + '_rate').value = rate;
    document.getElementById(type + '_is_edit').value = '1';
    document.getElementById(type + '_submit').value = _('Update Rate');
    document.getElementById(type + '_rate').focus();
}