/**
 * Budget Comparison JavaScript
 * Used by quickbudget_compare.php for viewing actuals vs budget
 */

function initComparison() {
    var viewBtn = document.getElementById('view-btn');
    if (!viewBtn) return;

    viewBtn.addEventListener('click', function(e) {
        var formData = new FormData(document.getElementById('compare-form'));
        fetch('quickbudget_compare.php?action=data', {
            method: 'POST',
            body: formData
        }).then(function(r) { return r.json(); }).then(function(data) {
            var html = '<table class="table table-bordered"><thead><tr><th>GL Account</th><th>Actual</th><th>Budget</th><th>Variance</th><th>% Variance</th></tr></thead><tbody>';
            var allGlAccounts = {};
            for (var gl in data.actuals) { allGlAccounts[gl] = true; }
            for (var gl in data.budget) { allGlAccounts[gl] = true; }
            Object.keys(allGlAccounts).sort().forEach(function(gl) {
                var actual = (data.actuals[gl] || 0).toFixed(2);
                var budget = (data.budget[gl] || 0).toFixed(2);
                var variance = (data.variance[gl] || 0).toFixed(2);
                var varianceNum = data.variance[gl] || 0;
                var budgetNum = data.budget[gl] || 0;
                var percent = budgetNum != 0 ? ((varianceNum / budgetNum) * 100).toFixed(2) : 0;
                var cls = varianceNum >= 0 ? 'variance-positive' : 'variance-negative';
                html += '<tr class="' + cls + '"><td>' + gl + '</td><td>' + actual + '</td><td>' + budget + '</td><td>' + variance + '</td><td>' + percent + '%</td></tr>';
            });
            html += '</tbody></table>';
            document.getElementById('comparison-results').innerHTML = html;
        });
    });
}