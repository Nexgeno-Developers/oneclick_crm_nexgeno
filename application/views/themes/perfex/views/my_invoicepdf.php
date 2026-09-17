<?php

defined('BASEPATH') or exit('No direct script access allowed');

$dimensions      = $pdf->getPageDimensions();
$available_width = $dimensions['wk'] - ($dimensions['rm'] + $dimensions['lm']);
$brand           = '#1B4F72';
$brand_light     = '#EEF5FB';
$border          = '#D6E3F0';
$assets_path     = FCPATH . 'uploads/company/invoice_assets' . DIRECTORY_SEPARATOR;
$stamp_path      = $assets_path . 'stamp.jpeg';
$scanner_path    = $assets_path . 'scanner.png';
$signature_path  = $assets_path . 'signature.jpeg';
$upi_id          = 'yespay.smessi10274701@yesbankltd';

$pdf->setPrintHeader(false);
$pdf->SetFooterMargin(10);
// Reserve bottom space so body content does not overlap pinned footer
$pdf->SetAutoPageBreak(true, 46);

hooks()->add_filter('process_pdf_signature_on_close', function () {
    return false;
});

if (!function_exists('oneclick_pdf_prepare_image')) {
    /**
     * Flatten PNG alpha and trim near-white padding for tighter PDF placement.
     */
    function oneclick_pdf_prepare_image($path)
    {
        if (empty($path) || !file_exists($path) || !extension_loaded('gd')) {
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
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $src, 0, 0, 0, 0, $width, $height);
        imagedestroy($src);

        $top = 0;
        $left = 0;
        $right = $width - 1;
        $bottom = $height - 1;
        $threshold = 245;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($canvas, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r < $threshold || $g < $threshold || $b < $threshold) {
                    $top = $y;
                    break 2;
                }
            }
        }
        for ($y = $height - 1; $y >= 0; $y--) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($canvas, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r < $threshold || $g < $threshold || $b < $threshold) {
                    $bottom = $y;
                    break 2;
                }
            }
        }
        for ($x = 0; $x < $width; $x++) {
            for ($y = $top; $y <= $bottom; $y++) {
                $rgb = imagecolorat($canvas, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r < $threshold || $g < $threshold || $b < $threshold) {
                    $left = $x;
                    break 2;
                }
            }
        }
        for ($x = $width - 1; $x >= 0; $x--) {
            for ($y = $top; $y <= $bottom; $y++) {
                $rgb = imagecolorat($canvas, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                if ($r < $threshold || $g < $threshold || $b < $threshold) {
                    $right = $x;
                    break 2;
                }
            }
        }

        $crop_w = max(1, $right - $left + 1);
        $crop_h = max(1, $bottom - $top + 1);
        $cropped = imagecreatetruecolor($crop_w, $crop_h);
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
$stamp_src     = oneclick_pdf_prepare_image($stamp_path);
$signature_src = oneclick_pdf_prepare_image($signature_path);
$logo_html     = pdf_logo_url();
$invoice_title = _l('invoice_pdf_heading');

$info_right = '<div style="text-align:right; color:#000; line-height:16px;">';
$info_right .= '<span style="font-size:22px; font-weight:bold; color:' . $brand . ';">' . $invoice_title . '</span><br />';
$info_right .= '<span style="font-size:13px; font-weight:bold;"># ' . $invoice_number . '</span>';

if ($status != Invoices_model::STATUS_PAID && $status != Invoices_model::STATUS_CANCELLED && get_option('show_pay_link_to_invoice_pdf') == 1
    && found_invoice_mode($payment_modes, $invoice->id, false)) {
    $info_right .= '<br /><a style="color:#1B4F72; text-decoration:none;" href="' . site_url('invoice/' . $invoice->id . '/' . $invoice->hash) . '">' . _l('view_invoice_pdf_link_pay') . '</a>';
}

$info_right .= '<br /><span style="font-size:10px;"><strong>' . _l('invoice_data_date') . '</strong> ' . _d($invoice->date) . '</span>';
if (!empty($invoice->duedate)) {
    $info_right .= '<br /><span style="font-size:10px;"><strong>' . _l('invoice_data_duedate') . '</strong> ' . _d($invoice->duedate) . '</span>';
}
if ($invoice->sale_agent && get_option('show_sale_agent_on_invoices') == 1) {
    $info_right .= '<br /><span style="font-size:10px;"><strong>' . _l('sale_agent_string') . ':</strong> ' . get_staff_full_name($invoice->sale_agent) . '</span>';
}
if ($invoice->project_id && get_option('show_project_on_invoice') == 1) {
    $info_right .= '<br /><span style="font-size:10px;"><strong>' . _l('project') . ':</strong> ' . get_project_name_by_id($invoice->project_id) . '</span>';
}
foreach ($pdf_custom_fields as $field) {
    $value = get_custom_field_value($invoice->id, $field['id'], 'invoice');
    if ($value == '') {
        continue;
    }
    $info_right .= '<br /><span style="font-size:10px;"><strong>' . $field['name'] . ':</strong> ' . $value . '</span>';
}
$info_right .= '</div>';

$left_col  = $swap == '1' ? $info_right : $logo_html;
$right_col = $swap == '1' ? $logo_html : $info_right;
pdf_multi_row($left_col, $right_col, $pdf, ($dimensions['wk'] / 2) - $dimensions['lm']);

$pdf->ln(4);

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
$company_email = get_option('billing_statement_company_email');
if ($company_email == '') {
    $company_email = get_option('smtp_email');
}
if ($company_phone != '') {
    $company_lines[] = '<b>Phone:</b> ' . $company_phone;
}
if ($company_email != '') {
    $company_lines[] = '<b>Email:</b> ' . $company_email;
}
$company_info = implode('<br />', $company_lines);

$client_info_html = format_customer_info($invoice, 'invoice', 'billing');
if ($invoice->include_shipping == 1 && $invoice->show_shipping_on_invoice == 1) {
    $client_info_html .= '<br /><b>' . _l('ship_to') . ':</b><br />';
    $client_info_html .= format_customer_info($invoice, 'invoice', 'shipping');
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
$info_boxes .= '<td width="48.5%" bgcolor="' . $brand . '" style="font-size:11px; color:#fff; background-color:' . $brand . ';"><b>' . $left_label . '</b></td>';
$info_boxes .= '<td width="3%"></td>';
$info_boxes .= '<td width="48.5%" bgcolor="' . $brand . '" style="font-size:11px; color:#fff; background-color:' . $brand . ';"><b>' . $right_label . '</b></td>';
$info_boxes .= '</tr>';
$info_boxes .= '<tr>';
$info_boxes .= '<td width="48.5%" bgcolor="' . $brand_light . '" style="font-size:11px; line-height:16px; color:#000; background-color:' . $brand_light . ';">' . $left_content . '</td>';
$info_boxes .= '<td width="3%"></td>';
$info_boxes .= '<td width="48.5%" bgcolor="' . $brand_light . '" style="font-size:11px; line-height:16px; color:#000; background-color:' . $brand_light . ';">' . $right_content . '</td>';
$info_boxes .= '</tr>';
$info_boxes .= '</table>';

$pdf->writeHTML($info_boxes, true, false, false, false, '');
$pdf->Ln(4);

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
$pdf->writeHTML($tblhtml, true, false, false, false, '');
$pdf->Ln(4);

$tbltotal = '<table cellpadding="5" cellspacing="0" style="font-size:' . ($font_size + 2) . 'px; border-collapse:collapse;">';
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
$scanner_html .= '<tr><td style="font-size:9px; color:' . $brand . ';"><b>SCAN TO PAY</b></td></tr>';
if ($scanner_src != '') {
    $scanner_html .= '<tr><td><img src="' . $scanner_src . '" width="88" /></td></tr>';
}
$scanner_html .= '<tr><td style="font-size:8px;"><b>UPI ID</b><br />' . $upi_id . '</td></tr>';
$scanner_html .= '</table>';

$bank_html = '<table cellpadding="6" cellspacing="0" style="width:100%; background-color:' . $brand_light . '; border-collapse:collapse;">';
$bank_html .= '<tr><td style="font-size:10px; color:#fff; background-color:' . $brand . ';"><b>BANK DETAILS</b></td></tr>';
$bank_html .= '<tr><td style="font-size:9px; line-height:14px; color:#000;">';
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

$pdf->writeHTML($payment_row, true, false, false, false, '');

if (get_option('total_to_words_enabled') == 1) {
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->writeHTMLCell('', '', '', '', _l('num_word') . ': ' . $CI->numberword->convert($invoice->total, $invoice->currency_name), 0, 1, false, true, 'C', true);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(2);
}

if (!empty($invoice->clientnote)) {
    $pdf->Ln(3);
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('invoice_note'), 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(1);
    $pdf->writeHTMLCell('', '', '', '', $invoice->clientnote, 0, 1, false, true, 'L', true);
}

if (count($invoice->payments) > 0 && get_option('show_transactions_on_invoice_pdf') == 1) {
    $pdf->Ln(4);
    $line = 'border-bottom-color:#000000;border-bottom-width:1px;border-bottom-style:solid;';
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('invoice_received_payments') . ':', 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(2);
    $payhtml = '<table width="100%" cellspacing="0" cellpadding="5" border="0">
        <tr style="color:#000;">
        <th width="25%" style="' . $line . '">' . _l('invoice_payments_table_number_heading') . '</th>
        <th width="25%" style="' . $line . '">' . _l('invoice_payments_table_mode_heading') . '</th>
        <th width="25%" style="' . $line . '">' . _l('invoice_payments_table_date_heading') . '</th>
        <th width="25%" style="' . $line . '">' . _l('invoice_payments_table_amount_heading') . '</th>
    </tr>';
    foreach ($invoice->payments as $payment) {
        $payment_name = $payment['name'];
        if (!empty($payment['paymentmethod'])) {
            $payment_name .= ' - ' . $payment['paymentmethod'];
        }
        $payhtml .= '<tr>
            <td>' . $payment['paymentid'] . '</td>
            <td>' . $payment_name . '</td>
            <td>' . _d($payment['date']) . '</td>
            <td>' . app_format_money($payment['amount'], $invoice->currency_name) . '</td>
        </tr>';
    }
    $payhtml .= '</table>';
    $pdf->writeHTML($payhtml, true, false, false, false, '');
}

if (found_invoice_mode($payment_modes, $invoice->id, true, true)) {
    $pdf->Ln(3);
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('invoice_html_offline_payment') . ':', 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);

    foreach ($payment_modes as $mode) {
        if (is_numeric($mode['id'])) {
            if (!is_payment_mode_allowed_for_invoice($mode['id'], $invoice->id)) {
                continue;
            }
        }
        if (isset($mode['show_on_pdf']) && $mode['show_on_pdf'] == 1) {
            $pdf->Ln(1);
            $pdf->Cell(0, 0, $mode['name'], 0, 1, 'L', 0, '', 0);
            $pdf->Ln(1);
            $pdf->writeHTMLCell('', '', '', '', $mode['description'], 0, 1, false, true, 'L', true);
        }
    }
}

$terms = !empty($invoice->terms) ? $invoice->terms : get_option('predefined_terms_invoice');
if (!empty($terms) && strip_tags($terms) === $terms) {
    $terms = nl2br($terms);
}

$terms_html = '<table cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse;">';
$terms_html .= '<tr><td bgcolor="' . $brand . '" style="font-size:11px; color:#fff; background-color:' . $brand . ';"><b>' . strtoupper(_l('terms_and_conditions')) . '</b></td></tr>';
$terms_html .= '<tr><td bgcolor="' . $brand_light . '" style="font-size:10px; line-height:14px; color:#000; background-color:' . $brand_light . ';">' . $terms . '</td></tr>';
$terms_html .= '</table>';

$terms_w  = $available_width * 0.56;
$sign_w   = $available_width * 0.42;
$sign_gap = $available_width * 0.02;
$col_w    = $sign_w / 2;
$stamp_w  = 14;
$sign_img_w = 16;
$footer_block_h = 40;

// Pin tightly to bottom of page
$page_h   = $pdf->getPageHeight();
$bottom_pad = 4;
$footer_y = $page_h - $footer_block_h - $bottom_pad;
if ($pdf->getY() > ($footer_y - 2)) {
    $pdf->AddPage();
}
$pdf->SetAutoPageBreak(false);
$pdf->SetY($footer_y);

$sign_x = $dimensions['lm'] + $terms_w + $sign_gap;

$pdf->writeHTMLCell($terms_w, '', $dimensions['lm'], $footer_y, $terms_html, 0, 0, false, true, 'LT', true);

$pdf->SetXY($sign_x, $footer_y);
$pdf->SetFont($font_name, 'B', 5.5);
$pdf->Cell($col_w, 3, 'PREPARED BY', 0, 0, 'C', false);
$pdf->Cell($col_w, 3, 'CHECKED AND APPROVED BY', 0, 1, 'C', false);

$img_y = $footer_y + 4;
if ($stamp_src != '' && file_exists($stamp_src)) {
    $pdf->Image($stamp_src, $sign_x + (($col_w - $stamp_w) / 2), $img_y, $stamp_w, 0, '', '', '', false, 300, '', false, false, 0);
}
if ($signature_src != '' && file_exists($signature_src)) {
    $pdf->Image($signature_src, $sign_x + $col_w + (($col_w - $sign_img_w) / 2), $img_y + 1.5, $sign_img_w, 0, '', '', '', false, 300, '', false, false, 0);
}

$name_y = $img_y + 15.5;
$pdf->SetXY($sign_x, $name_y);
$pdf->SetFont($font_name, 'B', 6);
$pdf->Cell($col_w, 3, 'IMTIYAZ SHAIKH', 0, 0, 'C', false);
$pdf->Cell($col_w, 3, 'SAMEER KHAN', 0, 1, 'C', false);
$pdf->SetX($sign_x);
$pdf->SetFont($font_name, '', 5.5);
$pdf->Cell($col_w, 2.8, 'Accountant', 0, 0, 'C', false);
$pdf->Cell($col_w, 2.8, 'Managing Director', 0, 1, 'C', false);
$pdf->SetFont($font_name, '', $font_size);
