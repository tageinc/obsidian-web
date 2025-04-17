<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class Delta extends Controller
{
    public function showDelta()
    {
        $path = public_path('Delta Analysis - Smart Panels.xlsx');
        $reader = new Xlsx();
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        // Extract the relevant columns
        $spb_values = array_column($data, 10); // Assuming 'spb (USD)' is the 11th column

        // Remove the header if necessary
        array_shift($spb_values);

        // Filter out non-numeric values
        $spb_values = array_filter($spb_values, 'is_numeric');

        // Calculate the accumulative SPB
        $accumulative_spb = array_sum($spb_values);

        // Check if the columns are not empty
        if (!empty($spb_values)) {
            $latest_spb_value = end($spb_values);
            $min_spb_value = min($spb_values);
            $max_spb_value = max($spb_values);
        } else {
            $latest_spb_value = 0;
            $min_spb_value = 0;
            $max_spb_value = 1;
        }

        return view('delta', compact('latest_spb_value', 'min_spb_value', 'max_spb_value', 'accumulative_spb'));
    }
}
