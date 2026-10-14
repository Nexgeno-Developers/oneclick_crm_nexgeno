<?php

defined('BASEPATH') or exit('No direct script access allowed');

$dimensions      = $pdf->getPageDimensions();
$available_width = $dimensions['wk'] - ($dimensions['rm'] + $dimensions['lm']);
$brand           = '#111111';
$brand_light     = '#F5F5F5';
$border          = '#CCCCCC';
$assets_path     = FCPATH . 'uploads/company/invoice_assets' . DIRECTORY_SEPARATOR;
$stamp_path      = $assets_path . 'stamp.png';
$scanner_path    = $assets_path . 'scanner.png';
$signature_path  = $assets_path . 'signature.png';
$upi_id          = 'yespay.smessi10274701@yesbankltd';

$pdf->setPrintHeader(false);
$pdf->SetFooterMargin(10);
// Reserve space for bank/scanner/totals + terms footer at bottom
$pdf->SetAutoPageBreak(true, 100);
// Slightly larger base font for items table and body text
$font_size = max((int) $font_size, 10) + 1;
$pdf->SetFont($font_name, '', $font_size);

hooks()->add_filter('process_pdf_signature_on_close', function () {
    return false;
});

if (!function_exists('oneclick_pdf_prepare_image')) {
    /**
     * Trim near-empty padding for tighter PDF placement.
     * Pass $preserve_alpha=true for stamp/signature so transparent PNG stays transparent.
     */
    function oneclick_pdf_prepare_image($path, $preserve_alpha = false)
    {
        if (empty($path) || !file_exists($path)) {
            return '';
        }

        if (!extension_loaded('gd')) {
            return $path;
        }

        $info = @getimagesize($path);
        if (!$info) {
            return $path;
        }

        if ($info[2] == IMAGETYPE_PNG) {
            $src = @imagecreatefrompng($path);
        } elseif ($info[2] == IMAGETYPE_JPEG) {
            $src = @imagecreatefromjpeg($path);
        } else {
            return $path;
        }

        if ($src === false) {
            return $path;
        }

        $width  = imagesx($src);
        $height = imagesy($src);
        $canvas = imagecreatetruecolor($width, $height);

        if ($preserve_alpha && $info[2] == IMAGETYPE_PNG) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
            imagealphablending($canvas, true);
            imagecopy($canvas, $src, 0, 0, 0, 0, $width, $height);
            imagealphablending($canvas, false);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagealphablending($canvas, true);
            imagecopy($canvas, $src, 0, 0, 0, 0, $width, $height);
        }
        imagedestroy($src);

        $top = 0;
        $left = 0;
        $right = $width - 1;
        $bottom = $height - 1;
        $threshold = 245;

        $is_content = function ($canvas, $x, $y) use ($preserve_alpha, $threshold) {
            $rgb = imagecolorat($canvas, $x, $y);
            if ($preserve_alpha) {
                $a = ($rgb & 0x7F000000) >> 24;
                if ($a >= 120) {
                    return false;
                }
            }
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            return ($r < $threshold || $g < $threshold || $b < $threshold);
        };

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($is_content($canvas, $x, $y)) {
                    $top = $y;
                    break 2;
                }
            }
        }
        for ($y = $height - 1; $y >= 0; $y--) {
            for ($x = 0; $x < $width; $x++) {
                if ($is_content($canvas, $x, $y)) {
                    $bottom = $y;
                    break 2;
                }
            }
        }
        for ($x = 0; $x < $width; $x++) {
            for ($y = $top; $y <= $bottom; $y++) {
                if ($is_content($canvas, $x, $y)) {
                    $left = $x;
                    break 2;
                }
            }
        }
        for ($x = $width - 1; $x >= 0; $x--) {
            for ($y = $top; $y <= $bottom; $y++) {
                if ($is_content($canvas, $x, $y)) {
                    $right = $x;
                    break 2;
                }
            }
        }

        $crop_w = max(1, $right - $left + 1);
        $crop_h = max(1, $bottom - $top + 1);
        $cropped = imagecreatetruecolor($crop_w, $crop_h);

        if ($preserve_alpha && $info[2] == IMAGETYPE_PNG) {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
            imagefill($cropped, 0, 0, $transparent);
            imagecopy($cropped, $canvas, 0, 0, $left, $top, $crop_w, $crop_h);
            imagedestroy($canvas);

            $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oct_pdf_' . md5($path . $crop_w . $crop_h . 'a') . '.png';
            @imagepng($cropped, $temp);
            imagedestroy($cropped);

            return file_exists($temp) ? $temp : $path;
        }

        $bg = imagecolorallocate($cropped, 255, 255, 255);
        imagefill($cropped, 0, 0, $bg);
        imagecopy($cropped, $canvas, 0, 0, $left, $top, $crop_w, $crop_h);
        imagedestroy($canvas);

        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oct_pdf_' . md5($path . $crop_w . $crop_h) . '.jpg';
        @imagejpeg($cropped, $temp, 95);
        imagedestroy($cropped);

        return file_exists($temp) ? $temp : $path;
    }
}

$scanner_src   = oneclick_pdf_prepare_image($scanner_path);
$stamp_src     = oneclick_pdf_prepare_image($stamp_path, true);
$signature_src = oneclick_pdf_prepare_image($signature_path, true);
// Prefer original assets on disk (more reliable on shared hosting than /tmp)
if (file_exists($scanner_path)) {
    $scanner_src = $scanner_path;
}
if ($stamp_src == '' && file_exists($stamp_path)) {
    $stamp_src = $stamp_path;
}
if ($signature_src == '' && file_exists($signature_path)) {
    $signature_src = $signature_path;
}

// Company logo — draw with Image() (HTML <img> fails on many prod hosts)
$logo_file = '';
$companyUploadPath = get_upload_path_by_type('company');
if (file_exists($assets_path . 'logo.png')) {
    $logo_file = $assets_path . 'logo.png';
} elseif (file_exists($companyUploadPath . 'oneclicklogo_trimmed.png')) {
    $logo_file = $companyUploadPath . 'oneclicklogo_trimmed.png';
} elseif (file_exists($companyUploadPath . 'oneclicklogo.png')) {
    $logo_file = $companyUploadPath . 'oneclicklogo.png';
} elseif (get_option('company_logo_dark') != '' && file_exists($companyUploadPath . get_option('company_logo_dark'))) {
    $logo_file = $companyUploadPath . get_option('company_logo_dark');
} elseif (get_option('company_logo') != '' && file_exists($companyUploadPath . get_option('company_logo'))) {
    $logo_file = $companyUploadPath . get_option('company_logo');
}

// Auto-trim transparent padding if possible
if (!empty($logo_file) && file_exists($logo_file) && !strpos($logo_file, '_trimmed')) {
    $prepared_logo = oneclick_pdf_prepare_image($logo_file, true);
    if (!empty($prepared_logo) && file_exists($prepared_logo)) {
        $logo_file = $prepared_logo;
    }
}

$logo_w_mm = 37;
$opt_logo_w = (float) get_option('pdf_logo_width');
if ($opt_logo_w >= 25 && $opt_logo_w <= 45) {
    $logo_w_mm = $opt_logo_w;
} elseif ($opt_logo_w > 45) {
    // If stored as px in Perfex (e.g. 120-150px), scale down proportionally
    $logo_w_mm = min(40, max(34, $opt_logo_w * 0.25));
}

$invoice_title = _l('invoice_pdf_heading');

$info_right = '<div style="text-align:right; color:#000; line-height:16px;">';
$info_right .= '<span style="font-size:24px; font-weight:bold; color:#000;">' . $invoice_title . '</span><br />';
$info_right .= '<span style="font-size:14px; font-weight:bold;"># ' . $invoice_number . '</span>';

if ($status != Invoices_model::STATUS_PAID && $status != Invoices_model::STATUS_CANCELLED && get_option('show_pay_link_to_invoice_pdf') == 1
    && found_invoice_mode($payment_modes, $invoice->id, false)) {
    $info_right .= '<br /><a style="color:#000; text-decoration:none;" href="' . site_url('invoice/' . $invoice->id . '/' . $invoice->hash) . '">' . _l('view_invoice_pdf_link_pay') . '</a>';
}

$info_right .= '<br /><span style="font-size:11px;"><strong>' . _l('invoice_data_date') . '</strong> ' . _d($invoice->date) . '</span>';
if (!empty($invoice->duedate)) {
    $info_right .= '<br /><span style="font-size:11px;"><strong>' . _l('invoice_data_duedate') . '</strong> ' . _d($invoice->duedate) . '</span>';
}
if ($invoice->sale_agent && get_option('show_sale_agent_on_invoices') == 1) {
    $info_right .= '<br /><span style="font-size:11px;"><strong>' . _l('sale_agent_string') . ':</strong> ' . get_staff_full_name($invoice->sale_agent) . '</span>';
}
if ($invoice->project_id && get_option('show_project_on_invoice') == 1) {
    $info_right .= '<br /><span style="font-size:11px;"><strong>' . _l('project') . ':</strong> ' . get_project_name_by_id($invoice->project_id) . '</span>';
}
foreach ($pdf_custom_fields as $field) {
    $value = get_custom_field_value($invoice->id, $field['id'], 'invoice');
    if ($value == '') {
        continue;
    }
    $info_right .= '<br /><span style="font-size:11px;"><strong>' . $field['name'] . ':</strong> ' . $value . '</span>';
}
$info_right .= '</div>';

// Start at Y=8 to remove excessive blank space above logo
$pdf->SetY(8);
$header_start_y = $pdf->getY();
$half_w         = $available_width / 2;

$logo_h_mm = 18;
if ($logo_file != '' && file_exists($logo_file)) {
    $logo_size = @getimagesize($logo_file);
    if ($logo_size && !empty($logo_size[0]) && !empty($logo_size[1])) {
        $logo_h_mm = ($logo_w_mm / $logo_size[0]) * $logo_size[1];
    }
}

if ($swap == '1') {
    $pdf->writeHTMLCell($half_w, '', $dimensions['lm'], $header_start_y, $info_right, 0, 0, false, true, 'L', true);
    if ($logo_file != '' && file_exists($logo_file)) {
        $pdf->Image($logo_file, $dimensions['lm'] + $half_w + 5, $header_start_y, $logo_w_mm, 0, '', '', '', false, 300, '', false, false, 0);
    }
} else {
    if ($logo_file != '' && file_exists($logo_file)) {
        $pdf->Image($logo_file, $dimensions['lm'], $header_start_y, $logo_w_mm, 0, '', '', '', false, 300, '', false, false, 0);
    }
    $pdf->writeHTMLCell($half_w, '', $dimensions['lm'] + $half_w, $header_start_y, $info_right, 0, 0, false, true, 'R', true);
}

// Position next section tightly below the header block
$pdf->SetY($header_start_y + $logo_h_mm + 2);

// Build compact company block — background ends just after email
$company_lines = [];
$company_name  = get_option('invoice_company_name');
if ($company_name != '') {
    $company_lines[] = '<b>' . $company_name . '</b>';
}
$company_address = trim(get_option('invoice_company_address'));
if ($company_address != '') {
    $company_lines[] = $company_address;
}
$city_state = trim(preg_replace('/\s+/', ' ', get_option('invoice_company_city') . ' ' . get_option('company_state')));
$city_state = trim($city_state, " \t\n\r\0\x0B-");
if ($city_state != '') {
    $company_lines[] = $city_state;
}
$zip_country = trim(get_option('invoice_company_country_code') . ' ' . get_option('invoice_company_postal_code'));
if ($zip_country != '') {
    $company_lines[] = $zip_country;
}
$company_vat = get_option('company_vat');
if ($company_vat != '') {
    $company_lines[] = _l('company_vat_number') . ': ' . $company_vat;
}
$company_phone = get_option('invoice_company_phonenumber');
$company_email = 'info@oneclicktravels.in';
$company_web   = 'www.oneclicktravels.in';
if ($company_phone != '') {
    $company_lines[] = '<b>Phone:</b> ' . $company_phone;
}
$company_lines[] = '<b>Email:</b> ' . $company_email . ' &nbsp;&nbsp; <b>Web:</b> ' . $company_web;
$company_info = implode('<br />', $company_lines);

$client_info_html = format_customer_info($invoice, 'invoice', 'billing');
// Shipping address intentionally omitted from PDF

// Primary contact phone & email on Bill To
$primary_contact_id = get_primary_contact_user_id($invoice->clientid);
if ($primary_contact_id) {
    $CI = &get_instance();
    $CI->db->select('email, phonenumber');
    $CI->db->where('id', $primary_contact_id);
    $primary_contact = $CI->db->get(db_prefix() . 'contacts')->row();
    if ($primary_contact) {
        if (!empty($primary_contact->phonenumber)) {
            $client_info_html .= '<br /><b>Phone:</b> ' . $primary_contact->phonenumber;
        }
        if (!empty($primary_contact->email)) {
            $client_info_html .= '<br /><b>Email:</b> ' . $primary_contact->email;
        }
    }
}

$client_info_html = preg_replace('/(<br\s*\/?>\s*)+/i', '<br />', trim($client_info_html));
$client_info_html = preg_replace('/(<br\s*\/?>\s*)+$/i', '', $client_info_html);

$company_info     = hooks()->apply_filters('invoicepdf_organization_info', $company_info, $invoice);
$client_info_html = hooks()->apply_filters('invoice_pdf_info', $client_info_html, $invoice);

$left_label    = $swap == '1' ? strtoupper(_l('invoice_bill_to')) : 'COMPANY DETAILS';
$right_label   = $swap == '1' ? 'COMPANY DETAILS' : strtoupper(_l('invoice_bill_to'));
$left_content  = $swap == '1' ? $client_info_html : $company_info;
$right_content = $swap == '1' ? $company_info : $client_info_html;

// Equal-height boxes sized to content only
$info_boxes = '<table cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse;">';
$info_boxes .= '<tr>';
$info_boxes .= '<td width="48.5%" bgcolor="' . $brand . '" style="font-size:12px; color:#fff; background-color:' . $brand . ';"><b>' . $left_label . '</b></td>';
$info_boxes .= '<td width="3%"></td>';
$info_boxes .= '<td width="48.5%" bgcolor="' . $brand . '" style="font-size:12px; color:#fff; background-color:' . $brand . ';"><b>' . $right_label . '</b></td>';
$info_boxes .= '</tr>';
$info_boxes .= '<tr>';
$info_boxes .= '<td width="48.5%" bgcolor="' . $brand_light . '" style="font-size:12px; line-height:17px; color:#000; background-color:' . $brand_light . ';">' . $left_content . '</td>';
$info_boxes .= '<td width="3%"></td>';
$info_boxes .= '<td width="48.5%" bgcolor="' . $brand_light . '" style="font-size:12px; line-height:17px; color:#000; background-color:' . $brand_light . ';">' . $right_content . '</td>';
$info_boxes .= '</tr>';
$info_boxes .= '</table>';

$pdf->writeHTML($info_boxes, true, false, false, false, '');
// Reduce space between customer detail boxes and items table header
$pdf->SetY($pdf->getY() - 1.5);

$items   = get_items_table_data($invoice, 'invoice', 'pdf');
$tblhtml = $items->table();
$heading_color = get_option('pdf_table_heading_color');
if (!empty($heading_color)) {
    $tblhtml = str_replace($heading_color, $brand, $tblhtml);
}
$heading_text = get_option('pdf_table_heading_text_color');
if (!empty($heading_text)) {
    $tblhtml = str_replace('color:' . $heading_text, 'color:#ffffff', $tblhtml);
}

// Add white column separator lines between header cells, with black borders on outer left (#) and outer right (Amount)
$tblhtml = preg_replace('/<th\b([^>]*)>/i', '<th$1 style="border-right:1px solid #ffffff;">', $tblhtml);
$tblhtml = preg_replace('/style="([^"]*)"\s+style="([^"]*)"/i', 'style="$1 $2"', $tblhtml);
$tblhtml = preg_replace('/(<tr\b[^>]*>\s*<th\b[^>]*\bstyle=")/i', '${1}border-left:1px solid #000000; ', $tblhtml);
$tblhtml = preg_replace('/(<th\b[^>]*\bstyle="[^"]*border-right:\s*1px\s+solid\s+)#ffffff([^"]*"[^>]*>(?:(?!<th).)*?<\/th>\s*<\/tr>)/is', '${1}#000000${2}', $tblhtml);

// Extend last column (Amount) so total column widths equal 100% (Perfex defaults sum to 99%)
preg_match_all('/<th\b[^>]*width=["\']([0-9.]+)[^"\']*["\']/i', $tblhtml, $w_matches);
if (!empty($w_matches[1])) {
    $total_w = array_sum($w_matches[1]);
    if ($total_w > 0 && $total_w < 100) {
        $diff = 100 - $total_w;
        $last_w = (float) end($w_matches[1]);
        $new_last_w = $last_w + $diff;

        $tblhtml = preg_replace('/(<th\b[^>]*\bwidth=["\'])([0-9.]+)[^"\']*([\'"][^>]*>(?:(?!<th).)*?<\/th>\s*<\/tr>)/is', '${1}' . $new_last_w . '%${3}', $tblhtml);
        $tblhtml = preg_replace('/(<td\b[^>]*\bwidth=["\'])([0-9.]+)[^"\']*([\'"][^>]*>(?:(?!<td).)*?<\/td>\s*<\/tr>)/is', '${1}' . $new_last_w . '%${3}', $tblhtml);
    }
}

// Add black vertical column lines to body cells (without horizontal row border lines)
$tblhtml = preg_replace('/<td\b([^>]*)>/i', '<td$1 style="border-right:1px solid #000000;">', $tblhtml);
$tblhtml = preg_replace('/style="([^"]*)"\s+style="([^"]*)"/i', 'style="$1 $2"', $tblhtml);
$tblhtml = preg_replace('/(<tr\b[^>]*>\s*<td\b[^>]*\bstyle=")/i', '${1}border-left:1px solid #000000; ', $tblhtml);

// Add left and right outer borders to the table
$tblhtml = str_replace('<table ', '<table style="border-collapse:collapse; border-left:1px solid #000000; border-right:1px solid #000000;" ', $tblhtml);

// Set line style and draw color to solid black before rendering table
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineStyle([
    'width' => 0.25,
    'cap'   => 'butt',
    'join'  => 'miter',
    'dash'  => 0,
    'color' => [0, 0, 0],
]);

// Render the unified table with ln=false so headers repeat on page breaks and stops at exact bottom of cells
$start_page = $pdf->getPage();
$pdf->writeHTML($tblhtml, false, false, false, false, '');
$items_end_y = $pdf->getY();
$end_page    = $pdf->getPage();

// Extract column widths from thead (robust to Perfex's 5%; semicolon formatting)
preg_match_all('/<th\b[^>]*width=["\']([0-9.]+)[^"\']*["\']/i', $tblhtml, $matches);
$col_x = [];
$cur_x = $dimensions['lm'];
$col_x[] = $cur_x;
if (!empty($matches[1])) {
    foreach ($matches[1] as $w) {
        $cur_x += ($available_width * ((float)$w / 100));
        $col_x[] = $cur_x;
    }
}
$col_x[count($col_x) - 1] = $dimensions['lm'] + $available_width;

$payment_block_h = 46;
$terms_block_h   = 34;
$gap_between     = 1.5;
$bottom_pad      = 3;
$page_h          = $pdf->getPageHeight();
$block_start     = $page_h - $payment_block_h - $gap_between - $terms_block_h - $bottom_pad;
$note_reserve    = !empty($invoice->clientnote) ? 14 : 0;
$target_bottom_y = $block_start - $note_reserve - 3;

// If table ends above Bank Details, extend column lines down to just above Bank Details
if ($items_end_y < $target_bottom_y) {
    foreach ($col_x as $x) {
        $pdf->Line($x, $items_end_y, $x, $target_bottom_y);
    }
    $pdf->Line($dimensions['lm'], $target_bottom_y, $dimensions['lm'] + $available_width, $target_bottom_y);
    $pdf->SetY($target_bottom_y);
} else {
    // Multi-page or full page: close table with the bottom horizontal line
    $pdf->Line($dimensions['lm'], $items_end_y, $dimensions['lm'] + $available_width, $items_end_y);
    $pdf->SetY($items_end_y);
}

$tbltotal = '<table cellpadding="5" cellspacing="0" style="font-size:' . ($font_size + 3) . 'px; border-collapse:collapse;">';
$tbltotal .= '<tr>
    <td align="right" width="58%" style="border:1px solid ' . $border . ';"><strong>' . _l('invoice_subtotal') . '</strong></td>
    <td align="right" width="42%" style="border:1px solid ' . $border . ';">' . app_format_money($invoice->subtotal, $invoice->currency_name) . '</td>
</tr>';

if (is_sale_discount_applied($invoice)) {
    $tbltotal .= '<tr>
        <td align="right" width="58%" style="border:1px solid ' . $border . ';"><strong>' . _l('invoice_discount');
    if (is_sale_discount($invoice, 'percent')) {
        $tbltotal .= ' (' . app_format_number($invoice->discount_percent, true) . '%)';
    }
    $tbltotal .= '</strong></td>
        <td align="right" width="42%" style="border:1px solid ' . $border . ';">-' . app_format_money($invoice->discount_total, $invoice->currency_name) . '</td>
    </tr>';
}

foreach ($items->taxes() as $tax) {
    $tbltotal .= '<tr>
        <td align="right" width="58%" style="border:1px solid ' . $border . ';"><strong>' . $tax['taxname'] . ' (' . app_format_number($tax['taxrate']) . '%)</strong></td>
        <td align="right" width="42%" style="border:1px solid ' . $border . ';">' . app_format_money($tax['total_tax'], $invoice->currency_name) . '</td>
    </tr>';
}

if ((int) $invoice->adjustment != 0) {
    $tbltotal .= '<tr>
        <td align="right" width="58%" style="border:1px solid ' . $border . ';"><strong>' . _l('invoice_adjustment') . '</strong></td>
        <td align="right" width="42%" style="border:1px solid ' . $border . ';">' . app_format_money($invoice->adjustment, $invoice->currency_name) . '</td>
    </tr>';
}

$tbltotal .= '<tr style="background-color:' . $brand . '; color:#fff;">
    <td align="right" width="58%"><strong>' . _l('invoice_total') . '</strong></td>
    <td align="right" width="42%"><strong>' . app_format_money($invoice->total, $invoice->currency_name) . '</strong></td>
</tr>';

if (count($invoice->payments) > 0 && get_option('show_total_paid_on_invoice') == 1) {
    $tbltotal .= '<tr>
        <td align="right" width="58%" style="border:1px solid ' . $border . ';"><strong>' . _l('invoice_total_paid') . '</strong></td>
        <td align="right" width="42%" style="border:1px solid ' . $border . ';">-' . app_format_money(sum_from_table(db_prefix() . 'invoicepaymentrecords', [
        'field' => 'amount',
        'where' => [
            'invoiceid' => $invoice->id,
        ],
    ]), $invoice->currency_name) . '</td>
    </tr>';
}

if (get_option('show_credits_applied_on_invoice') == 1 && $credits_applied = total_credits_applied_to_invoice($invoice->id)) {
    $tbltotal .= '<tr>
        <td align="right" width="58%" style="border:1px solid ' . $border . ';"><strong>' . _l('applied_credits') . '</strong></td>
        <td align="right" width="42%" style="border:1px solid ' . $border . ';">-' . app_format_money($credits_applied, $invoice->currency_name) . '</td>
    </tr>';
}

if (get_option('show_amount_due_on_invoice') == 1 && $invoice->status != Invoices_model::STATUS_CANCELLED) {
    $tbltotal .= '<tr style="background-color:' . $brand_light . ';">
        <td align="right" width="58%" style="border:1px solid ' . $border . ';"><strong>' . _l('invoice_amount_due') . '</strong></td>
        <td align="right" width="42%" style="border:1px solid ' . $border . ';"><strong>' . app_format_money($invoice->total_left_to_pay, $invoice->currency_name) . '</strong></td>
    </tr>';
}
$tbltotal .= '</table>';

$scanner_html = '<table cellpadding="3" cellspacing="0" style="width:100%; text-align:center;">';
$scanner_html .= '<tr><td style="font-size:10px; color:#000;"><b>SCAN TO PAY</b></td></tr>';
// Reserve space for QR + UPI label — both drawn after writeHTML (HTML img often fails on prod)
$scanner_html .= '<tr><td style="height:110px;">&nbsp;</td></tr>';
$scanner_html .= '</table>';

$bank_html = '<table cellpadding="6" cellspacing="0" style="width:100%; background-color:' . $brand_light . '; border-collapse:collapse;">';
$bank_html .= '<tr><td style="font-size:11px; color:#fff; background-color:' . $brand . ';"><b>BANK DETAILS</b></td></tr>';
$bank_html .= '<tr><td style="font-size:10px; line-height:15px; color:#000;">';
$bank_html .= '<b>AC NAME:</b> ONE CLICK TRAVEL<br />';
$bank_html .= '<b>AC NO:</b> 021126900000607<br />';
$bank_html .= '<b>BRANCH:</b> S V ROAD KHAR<br />';
$bank_html .= '<b>IFSC CODE:</b> YESB0000211<br />';
$bank_html .= '<b>ACCOUNT TYPE:</b> CURRENT ACCOUNT<br />';
$bank_html .= '<b>BANK:</b> YES BANK LTD';
$bank_html .= '</td></tr></table>';

$payment_row = '<table cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse;">';
$payment_row .= '<tr>';
$payment_row .= '<td width="42%" style="vertical-align:top; padding-right:6px;">' . $bank_html . '</td>';
$payment_row .= '<td width="22%" style="vertical-align:top; text-align:center;">' . $scanner_html . '</td>';
$payment_row .= '<td width="36%" style="vertical-align:top;">' . $tbltotal . '</td>';
$payment_row .= '</tr></table>';

// Do not write payment_row yet — pinned above terms at page bottom

if (!empty($invoice->clientnote)) {
    $pdf->Ln(3);
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('invoice_note'), 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(1);
    $pdf->writeHTMLCell('', '', '', '', $invoice->clientnote, 0, 1, false, true, 'L', true);
}

// Transactions / payments table intentionally omitted from invoice PDF

// Skip Perfex offline payment modes — bank details are already in the custom BANK DETAILS box

$default_terms = "CASH CHEQUE CHEQUE VERY IMP. : Payment to be made to the cashier & printed Official Receipt must be obtained.<br />"
    . ": All cheques / demand drafts in payment of bills must be crossed 'A/c Payee Only'<br />"
    . ": and drawn in favour of 'ONE CLICK TRAVEL'.<br />"
    . "LATE PAYMENT : Interest @ 24% per annum will be charged on all outstanding bills after due date.<br />"
    . ": Kindly check all details carefully to avoid un-necessary complications.";

$terms = !empty($invoice->terms) ? $invoice->terms : get_option('predefined_terms_invoice');
if (trim(strip_tags((string) $terms)) == '') {
    $terms = $default_terms;
} elseif (strip_tags($terms) === $terms) {
    $terms = nl2br($terms);
}

$terms_html = '<table cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse;">';
$terms_html .= '<tr><td bgcolor="' . $brand . '" style="font-size:12px; color:#fff; background-color:' . $brand . ';"><b>' . strtoupper(_l('terms_and_conditions')) . '</b></td></tr>';
$terms_html .= '<tr><td bgcolor="' . $brand_light . '" style="font-size:11px; line-height:15px; color:#000; background-color:' . $brand_light . ';">' . $terms . '</td></tr>';
$terms_html .= '</table>';

$terms_w    = $available_width * 0.75;
$sign_w     = $available_width * 0.23;
$sign_gap   = $available_width * 0.02;
$col_w      = $sign_w / 2;
$stamp_w    = 18;
$sign_img_w = 16;
$payment_block_h = 46;
$terms_block_h   = 34;
$gap_between     = 1.5;
$bottom_pad      = 3;

// Pin bank/scanner/totals just above terms at page bottom (tight gap)
$page_h      = $pdf->getPageHeight();
$block_start = $page_h - $payment_block_h - $gap_between - $terms_block_h - $bottom_pad;
if ($pdf->getY() > ($block_start - 2)) {
    $pdf->AddPage();
}
$pdf->SetAutoPageBreak(false);
$pdf->SetY($block_start);
$pdf->writeHTML($payment_row, true, false, false, false, '');

// Draw QR + UPI ID with Image()/Cell — avoids HTML overlap (works on prod)
$y_after_payment = $pdf->getY();
$scanner_col_x = $dimensions['lm'] + ($available_width * 0.42);
$scanner_col_w = $available_width * 0.22;
$scanner_img_w = 20;
$scanner_img_x = $scanner_col_x + (($scanner_col_w - $scanner_img_w) / 2);
$scanner_img_y = $block_start + 5.5;
if ($scanner_src != '' && file_exists($scanner_src)) {
    $pdf->Image($scanner_src, $scanner_img_x, $scanner_img_y, $scanner_img_w, 0, '', '', '', false, 300, '', false, false, 0);
}
$upi_label_y = $scanner_img_y + $scanner_img_w + 1.5;
$pdf->SetXY($scanner_col_x, $upi_label_y);
$pdf->SetFont($font_name, 'B', 8);
$pdf->Cell($scanner_col_w, 3.5, 'UPI ID', 0, 2, 'C', false, '', 0, false, 'T', 'M');
// Wider than QR column + slight scale so full UPI stays on one line
$upi_text_w = $scanner_col_w + 16;
$upi_text_x = $scanner_col_x + (($scanner_col_w - $upi_text_w) / 2);
$pdf->SetFont($font_name, '', 6.5);
$pdf->SetXY($upi_text_x, $pdf->getY());
$pdf->Cell($upi_text_w, 3.2, $upi_id, 0, 1, 'C', false, '', 1);
$pdf->SetY($y_after_payment);
$pdf->SetFont($font_name, '', $font_size);

if (get_option('total_to_words_enabled') == 1) {
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->writeHTMLCell('', '', '', '', _l('num_word') . ': ' . $CI->numberword->convert($invoice->total, $invoice->currency_name), 0, 1, false, true, 'C', true);
    $pdf->SetFont($font_name, '', $font_size);
}

// Place terms immediately under bank/scanner/totals
$footer_y = $pdf->getY() + $gap_between;
$max_footer_y = $page_h - $terms_block_h - $bottom_pad;
if ($footer_y > $max_footer_y) {
    $footer_y = $max_footer_y;
}
$pdf->SetY($footer_y);

$sign_x = $dimensions['lm'] + $terms_w + $sign_gap;

$pdf->writeHTMLCell($terms_w, '', $dimensions['lm'], $footer_y, $terms_html, 0, 0, false, true, 'LT', true);

// Single approval block: signature then stamp, then Sameer Khan
$pdf->SetXY($sign_x, $footer_y);
$pdf->SetFont($font_name, 'B', 6.5);
$pdf->MultiCell($sign_w, 3.2, 'CHECKED AND APPROVED BY', 0, 'C', false, 1);

$img_y = $pdf->getY() + 0.5;
// Side by side, very close: signature then stamp
$pair_gap = 1;
$pair_w   = $sign_img_w + $pair_gap + $stamp_w;
$pair_x   = $sign_x + (($sign_w - $pair_w) / 2);

if ($signature_src != '' && file_exists($signature_src)) {
    $pdf->Image($signature_src, $pair_x, $img_y + 1.5, $sign_img_w, 0, '', '', '', false, 300, '', false, false, 0);
}
if ($stamp_src != '' && file_exists($stamp_src)) {
    $pdf->Image($stamp_src, $pair_x + $sign_img_w + $pair_gap, $img_y, $stamp_w, 0, '', '', '', false, 300, '', false, false, 0);
}

$name_y = $img_y + max($stamp_w, 12) + 1;
$pdf->SetXY($sign_x, $name_y);
$pdf->SetFont($font_name, 'B', 7);
$pdf->Cell($sign_w, 3.2, 'SAMEER KHAN', 0, 1, 'C', false);
$pdf->SetX($sign_x);
$pdf->SetFont($font_name, '', 6.5);
$pdf->Cell($sign_w, 3, 'Managing Director', 0, 1, 'C', false);
$pdf->SetFont($font_name, '', $font_size);

// Watermark: company logo enlarged in the center of each page with transparency
if ($logo_file != '' && file_exists($logo_file)) {
    $wm_w = 115;
    $logo_size = @getimagesize($logo_file);
    if (!empty($logo_size[0]) && !empty($logo_size[1])) {
        $aspect = $logo_size[0] / $logo_size[1];
        if ($aspect < 0.8) {
            $wm_h = 100;
            $wm_w = $wm_h * $aspect;
        } else {
            $wm_w = 115;
            $wm_h = $wm_w / $aspect;
        }
    } else {
        $wm_w = 115;
        $wm_h = 35;
    }

    $num_pages = $pdf->getNumPages();
    $orig_page = $pdf->getPage();

    for ($p = 1; $p <= $num_pages; $p++) {
        $pdf->setPage($p);
        $p_dim = $pdf->getPageDimensions();
        $p_w   = $p_dim['wk'];
        $p_h   = $p_dim['hk'];
        $wm_x  = ($p_w - $wm_w) / 2;
        $wm_y  = ($p_h - $wm_h) / 2;

        $pdf->SetAlpha(0.10);
        $pdf->Image($logo_file, $wm_x, $wm_y, $wm_w, $wm_h, '', '', '', false, 300, '', false, false, 0);
        $pdf->SetAlpha(1.0);
    }

    $pdf->setPage($orig_page);
}

