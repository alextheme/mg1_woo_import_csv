<?php

require_once 'helpers.php';
require_once 'class-shbb-import-csv.php';

$shbb = new Shbb_import_csv(array(
    'file_in' => 'src/catalog_product_orig.csv',
//    'file_in' => 'src/catalog_product.csv',
    'file_out' => 'dest/import.csv',
    'images_url_dir' => 'https://polishpostershop.entsolve1.pl/import_media',
//    'create_csv_limit_rows' => [20, 5],
));

print_pre($shbb->data_out);

// Вивести пусті атрибути
//$shbb->get_attributes_and_all_unique_values(0, 0);
//print_pre($shbb->attr_values);
//foreach ($shbb->attr_values as $a_key => $a_value) {
//    if (count($a_value) === 1 && empty($a_value[0])) {
//        echo $a_key, '<br>';
//    }
//}
