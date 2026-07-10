<?php
declare(strict_types=1);

class RateSectionRenderer
{
    public static function render(string $type, string $label, string $refLabel, array $rates, array $options, int $perPage, string $pageParam, bool $showCodeWithRef = false): string
    {
        $rateItems = [];
        foreach ($rates as $ref => $rate) {
            $rateItems[] = ['ref' => $ref, 'rate' => $rate, 'name' => $options[$ref] ?? $ref];
        }
        $totalItems = count($rateItems);
        $totalPages = max(1, ceil($totalItems / $perPage));
        $pageNum = min(1, (int)($_GET[$pageParam] ?? 1));
        $offset = ($pageNum - 1) * $perPage;
        $displayItems = array_slice($rateItems, $offset, $perPage);

        $output = "<div class='col-md-6'>";
        $output .= "<div class='card mb-3'>";
        $output .= "<div class='card-header'>" . _($label) . "</div>";
        $output .= "<div class='card-body'>";

        $output .= "<table class='table table-sm table-striped'>";
        $output .= "<thead><tr><th>" . _($refLabel) . "</th><th>" . _("Rate") . "</th><th>" . _("Actions") . "</th></tr></thead>";
        $output .= "<tbody>";

        $odd = true;
        foreach ($displayItems as $row) {
            if (empty($row['ref'])) {
                continue;
            }
            $odd = !$odd;
            $output .= "<tr" . ($odd ? '' : " class=\"tr_alt\"") . ">";
            if ($showCodeWithRef) {
                $output .= "<td>" . htmlspecialchars((string)$row['ref'] . ' - ' . $row['name']) . "</td>";
            } else {
                $output .= "<td>" . htmlspecialchars((string)$row['ref']) . "</td>";
            }
            $output .= "<td>" . htmlspecialchars((string)$row['rate']) . "</td>";
            $output .= "<td><button type='button' class='btn btn-sm btn-secondary' onclick=\"editRate('{$type}', '{$row['ref']}', {$row['rate']})\">" . _("Edit") . "</button></td>";
            $output .= "</tr>";
        }
        if (empty($displayItems)) {
            $output .= "<tr><td colspan='3' class='text-center'>" . _("No " . strtolower($label) . " defined") . "</td></tr>";
        }
        $output .= "</tbody></table>";

        $existingRatesJson = json_encode($rates);
        $output .= "<form method='post' action='quickbudget_config.php?action=save' id='{$type}-form' class='p-2 border rounded'>";
        $output .= "<input type='hidden' name='type' value='{$type}'>";
        $output .= "<input type='hidden' name='per_page' value='$perPage'>";
        $output .= "<input type='hidden' name='is_edit' id='{$type}_is_edit' value='0'>";
        $output .= "<select name='reference' id='{$type}_ref' class='form-control mb-2' onchange=\"setRateFromSelect('{$type}', this.value)\">";
        foreach ($options as $id => $name) {
            if (empty($id)) {
                continue;
            }
            $selected = isset($rates[$id]) ? ' selected' : '';
            $output .= "<option value='" . htmlspecialchars((string)$id) . "'$selected>" . htmlspecialchars((string)$name) . "</option>";
        }
        $output .= "</select>";
        $output .= "<input type='number' step='any' name='rate' id='{$type}_rate' value='' class='form-control mb-2' placeholder='Rate (e.g., 1.03 for 3%)'>";
        $output .= "<input type='submit' id='{$type}_submit' class='btn btn-primary' value='" . _("Save {$label}") . "'>";
        $output .= "</form>";

        $output .= "<script src='assets/rate-section.js'></script>";
        $output .= "<script>var existingRates_{$type} = $existingRatesJson;";
        $output .= "function setRateFromSelect(type, value) { setRateFromSelect_js(type, value, existingRates_{$type}); }";
        $output .= "function editRate(type, ref, rate) { editRate_js(type, ref, rate); }";
        $output .= "</script>";

        if ($totalPages > 1) {
            $output .= "<div class='pagination'>";
            for ($i = 1; $i <= $totalPages; $i++) {
                $active = $i === $pageNum ? ' font-weight-bold' : '';
                $output .= "<a href='quickbudget_config.php?per_page=$perPage&{$pageParam}=$i' class='mx-1$active'>$i</a>";
            }
            $output .= "</div>";
        }

        $output .= "</div></div>";
        return $output;
    }

    public static function renderTypeCache(array $rates): string
    {
        $output = "<div class='col-md-6'>";
        $output .= "<div class='card mb-3' style='border: 1px solid #ddd;'>";
        $output .= "<div class='card-header'>" . _("Type Rate Cache") . "</div>";
        $output .= "<div class='card-body'>";
        $output .= "<table class='table table-sm table-bordered' style='font-size: 0.85em;'>";
        $output .= "<thead><tr><th>" . _("Name") . "</th><th>" . _("Rate") . "</th></tr></thead>";
        $output .= "<tbody>";
        
        $typeRates = $rates['type'] ?? [];
        if (!empty($typeRates)) {
            foreach ($typeRates as $name => $rate) {
                $output .= "<tr><td>" . htmlspecialchars((string)$name) . "</td><td>" . htmlspecialchars((string)$rate) . "</td></tr>";
            }
        } else {
            $output .= "<tr><td colspan='2' class='text-center'>" . _("No type rates configured") . "</td></tr>";
        }
        
        $output .= "</tbody></table>";
        $output .= "</div></div></div>";
        return $output;
    }
}