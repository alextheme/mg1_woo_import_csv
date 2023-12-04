<?php

class Shbb_import_csv
{
    public $file_in = '';
    public $file_out = '';
    public $images_url_dir = '';

    /**
     * @var array Products
     */
    public $products = array();
    public $exclude_fields = array();
    public $only_fields = array(
        '_type',
        'sku',
        'name',
        'featured',
        'visibility',
        'short_description',
        'description',
        'is_in_stock',
        'qty',
        'min_qty',
        'weight',
        'price',
        '_category',
        'image',

        /* Attributes */
        'countries',
        'delivery_time',
        'designer',
        'director',
        'edition',
        'filmtitle',
        'known',
        'options_container',
        'poster_condition',
        'print_tech',
        'size',
        'starring',
        'meta_description',
        'meta_keyword',
        'meta_title',
        'msrp_display_actual_price_type',
        'msrp_enabled',

        /* Images Labels */
        'image_label',
        'small_image_label',
        'thumbnail_label',
        '_media_lable',

        );
    public $column_correspondence = array();
    public $data_out = array();



    public function __construct($args = array())
    {
        $this->file_in = key_exists('file_in', $args) ? $args['file_in'] : 'src/export.csv';
        $this->file_out = key_exists('file_out', $args) ? $args['file_out'] : 'dest/import.csv';;
        $this->images_url_dir = key_exists('images_url_dir', $args) ? $args['images_url_dir'] : 'http://localhost/october/import_media';
        $limit_rows = key_exists('create_csv_limit_rows', $args) ? $args['create_csv_limit_rows'] : [0,0];
        if (count($limit_rows) === 0) $limit_rows = [0,0];
        if (count($limit_rows) === 1) $limit_rows = [$limit_rows[0], 0];

        $this->init_cols();
        $this->parse_csv();
        $this->create_output_data();
        $this->create_csv($limit_rows);
    }

    /**
     * Reading a CSV file with fgetcsv
     *
     * @param string $path_to_csv_file    The path to the CSV file
     * @param array &$result              Stores the data in the reference variable.
     */
    private function read_csv($path_to_csv_file, &$result) {
        $handle = fopen($path_to_csv_file, 'r');

        if(!$handle){
            return false;
        }

        while(false !== ($data = fgetcsv($handle, null, ','))) {
            $result[] = $data;
        }

        return true;
    }

    private function parse_csv() {
        $response = [];
        if(!$this->read_csv($this->file_in, $response)){
            echo "CSV file could not be opened.";
        }

        $sku_prev = '';
        $table_header = null;

        foreach($response as $row_number => $data) {

            // Зберегти імена полів в окремому масиві
            if ($row_number === 0) {
                $table_header = array_map('trim', $data);
                continue;
            }

            // Поточний (штрих)код продукту з першої колонки
            $sku = $data[0];

            if (empty($sku)) {
                $sku = $sku_prev;
            }

            if (empty($sku) && empty($sku_prev)) continue;

            $new_product = !empty($sku) && !empty($sku_prev) && ($sku !== $sku_prev); // true or false

            // При переході до нового товару в списку,
            // переведемо всі дані в рядки,
            // попередньо їх обробивши
            if ($new_product) {
                $this->array_values_to_string_value($sku_prev);
            }

            // Створити масив масивів всіх продуктів. Ключі - імена полів в БД
            foreach ($data as $col_number => $val) {

                $column_header = $table_header[$col_number];

                // Фільтр полів
                if (count($this->exclude_fields) && in_array($column_header, $this->exclude_fields)) continue;
                if (count($this->only_fields) && !in_array($column_header, $this->only_fields)) continue;

                // Поточне значення
                $value = trim($val);

                // Створюємо масиви
                if (!key_exists($sku, $this->products)) {
                    $this->products[$sku] = array();
                }
                if (!key_exists($column_header, $this->products[$sku])) {
                    $this->products[$sku][$column_header] = array();
                }

                // Зберігаємо значення.
                // Якщо значень для одного товару буде кілька,
                // вони всі будуть додані до масиву
                // а потім будуть оброблені, щоб перевести в рядок.
                $this->products[$sku][$column_header][] = $value;
            }

            $sku_prev = $sku;
        }

        // Обробка даних останнього товару
        $this->array_values_to_string_value($sku_prev);
    }

    private function get_img_label($product)
    {
        $labelList = [
            $product['image_label'],
            $product['small_image_label'],
            $product['thumbnail_label'],
            $product['_media_lable'],
        ];

        $img_labels = array_map(function($el){if(is_string($el))return array($el);return $el;}, $labelList);

        unset($product['image_label']);
        unset($product['small_image_label']);
        unset($product['thumbnail_label']);
        unset($product['_media_lable']);

        return array_unique(array_merge(...$img_labels));
    }

    private function replace_commas_with_dots_in_numbers($str)
    {
        return preg_replace_callback(
            '/(\d+),(\d+)/',
            function($m) { return $m[1] . '.' . $m[2]; },
            preg_replace_callback(
                '/(\d+),(\d+)/',
                function($m) { return $m[1] . '.' . $m[2]; },
                $str
            )
        );
    }

    private function array_values_to_string_value($sku)
    {
        $product = $this->products[$sku];

        $product['main_image_label'] = $this->get_img_label($product);

        //print_pre($product, count($this->only_fields));

        foreach ($product as $attr => $value) {
            $filter = array_values(array_unique(array_filter($value)));
            $string_value = '';

            if (count($filter) === 0) {
                //$string_value = '';
            }

            elseif ($attr === 'visibility') {
                switch ($filter[0]) {
                    case 1: $string_value = 'hidden'; break;
                    case 2: $string_value = 'catalog'; break;
                    case 3: $string_value = 'search'; break;
                    default: $string_value = 'visible'; break;
                }
            }

            elseif ($attr === 'featured') {
                $string_value = in_array($filter[0], ['Nein', 'No']) ? 0 : 1;
            }

            elseif ($attr === '_category') {
                $string_value = implode(', ', $filter);
            }

            elseif ($attr === 'image') {
                $string_value = $this->images_url_dir . $filter[0];
            }

            elseif ($attr === 'size') {
                $string_value = $this->replace_commas_with_dots_in_numbers($filter[0]);
            }

            elseif (count($filter) === 1) {
                $string_value = $filter[0];
            }

            else {
                $string_value = implode(' || ', $filter);
            }

            $product[$attr] = $string_value;
        }

        //print_pre($product, count($product));
        $this->products[$sku] = $product;
    }

    private function init_cols() {

        // Відповідність полів вихідного та вхідного файлів
        $main_from_to = array(
            '_type' => 'Type',
            'sku' => 'SKU',
            'name' => 'Name',
            'featured' => 'Is featured?',
            'visibility' => 'Visibility in catalog',
            'short_description' => 'Short description',
            'description' => 'Description',
            'is_in_stock' => 'In stock?',
            'qty' => 'Stock',
            'min_qty' => 'Low stock amount',
            'weight' => 'Weight (kg)',
            'price' => 'Regular price',
            '_category' => 'Categories',
            'image' => 'Images',
        );

        // Поля, дані яких попадуть в атрибути товару
        $attr_from_to = array(
            'countries' => 'Countries',
            'delivery_time' => 'Delivery Time',
            'designer' => 'Designer',
            'director' => 'Director',
            'edition' => 'Edition',
            'filmtitle' => 'Film Title',
            'known' => 'Known',
            'options_container' => 'Options Container',
            'poster_condition' => 'Poster Condition',
            'print_tech' => 'Print Technology',
            'size' => 'Size',
            'starring' => 'Starring',
            'main_image_label' => 'Image Label',
            'meta_description' => 'Meta Description',
            'meta_keyword' => 'Meta Keyword',
            'meta_title' => 'Meta Title',
            'msrp_display_actual_price_type' => 'Display Actual Price',
            'msrp_enabled' => 'Apply MAP',
        );

        // Стандартні поля для WooCommerce *.scv файла імпорту товарів
        // Якщо в масиві "$main_from_to" є відповідне поле вихідного і вхідного файлів
        // то дані будуть підтягуватися з імпортованого файла,
        // інакше, буде підставлене значення за замовчанням з цього масиву.
        $cols_output = array(
            'ID' => '',
            'Type' => '',
            'SKU' => '',
            'Name' => '',
            'Published' => 1,
            'Is featured?' => '',
            'Visibility in catalog' => '',
            'Short description' => '',
            'Description' => '',
            'Date sale price starts' => '',
            'Date sale price ends' => '',
            'Tax status' => '',
            'Tax class' => '',
            'In stock?' => '',
            'Stock' => '',
            'Low stock amount' => '',
            'Backorders allowed?' => 0,
            'Sold individually?' => 0,
            'Weight (kg)' => '',
            'Length (cm)' => '',
            'Width (cm)' => '',
            'Height (cm)' => '',
            'Allow customer reviews?' => 1,
            'Purchase note' => '',
            'Sale price' => '',
            'Regular price' => '',
            'Categories' => '',
            'Tags' => '',
            'Shipping class' => '',
            'Images' => '',
            'Download limit' => '',
            'Download expiry days' => '',
            'Parent' => '',
            'Grouped products' => '',
            'Upsells' => '',
            'Cross-sells' => '',
            'External URL' => '',
            'Button text' => '',
            'Position' => 0,
            'Meta: _wpcom_is_markdown' => 1,
        );

        // Синхронізувати масиви "$main_from_to" і "$cols_output"
        foreach ($cols_output as $col => $value) {
            $key = array_search($col, $main_from_to);
            if ($key !== false) {
                $cols_output[$col] = $key;
            }
        }


        $this->column_correspondence = array('main_from_to' => $main_from_to, 'attr_from_to' => $attr_from_to, 'cols_output' => $cols_output);
    }

    private function create_output_data() {

        $max_count_attr = 0;

        foreach ($this->products as $product) {

            $cols_main = array();
            $cols_attr = array();
            $index_attr = 0;

            // main_from_to -- '_type' => 'Type',
            // attr_from_to -- 'print_tech' => 'Print Technology',
            // cols_output  -- 'Type' => '_type',

            // main columns
            foreach ($this->column_correspondence['cols_output'] as $output => $src) {

                if (key_exists($src, $this->column_correspondence['main_from_to'])) {
                    $cols_main[$output] = $product[$src];
                } else {
                    $cols_main[$output] = $src;
                }

            }

            // attributes column
            foreach ($this->column_correspondence['attr_from_to'] as $attr_from => $attr_to) {

                $value = $product[$attr_from];

                if (!empty($value)) {
                    $index_attr++;
                    $max_count_attr = max($index_attr, $max_count_attr);

                    $cols_attr['Attribute ' . $index_attr . ' name'] = $attr_to;
                    $cols_attr['Attribute ' . $index_attr . ' value(s)'] = $value;
                    $cols_attr['Attribute ' . $index_attr . ' visible'] = 1;
                    $cols_attr['Attribute ' . $index_attr . ' global'] = 1;
                }
            }

            $this->data_out[] = array('column_main' => $cols_main, 'column_attr' => $cols_attr);
        }

        // Добавити пусті поля для атрибутів.
        for ($i = 0; $i < count($this->data_out); $i++) {

            // ÷4 - на кожен атрибут відводиться по 4 колонки
            $count_exist_attributes = count($this->data_out[$i]['column_attr']) / 4;

            for ($j = 1; $j <= $max_count_attr - $count_exist_attributes; $j++) {
                $ind = $j + $count_exist_attributes;

                $cols_attr['Attribute ' . $ind . ' name'] = '';
                $cols_attr['Attribute ' . $ind . ' value(s)'] = '';
                $cols_attr['Attribute ' . $ind . ' visible'] = '';
                $cols_attr['Attribute ' . $ind . ' global'] = '';
            }

            $this->data_out[$i]['column_attr'] = $cols_attr;
        }

        // Видалити зайві колонки атрибутів та
        // Об'єднати всі поля в один масив
        for ($i = 0; $i < count($this->data_out); $i++) {
            $arr_merge = array_merge($this->data_out[$i]['column_main'], $this->data_out[$i]['column_attr']);
            $this->data_out[$i] = $arr_merge;
        }
    }

    private function create_csv($max_row = array(0,0)) {

        $limit_row = $max_row[0];
        $first_file_limit_row = $max_row[1];

        if ($limit_row === 0) {

            $filename = $this->file_out;

            $fileopen = fopen($filename, 'w');
            if ($fileopen === false) die('Error opening the file ' . $filename);

            // Друк шапки таблиці
            fputcsv($fileopen, array_keys($this->data_out[0]));
            // Друк боді таблиці
            foreach ($this->data_out as $item) {
                fputcsv($fileopen, $item);
            }

            fclose($fileopen);

        } else {

            $true = true;
            $start = 0;
            $file_index = 0;

            $finfo = pathinfo($this->file_out);

            // Цикл для того, якщо потрібно розбити імпорт файлів на декілька файлів
            while ($true) {
                $filename = $finfo['dirname'] . '/' . $finfo['filename'] . '_' . $file_index . '.' . $finfo['extension'];

                $fileopen = fopen($filename, 'w');
                if ($fileopen === false) die('Error opening the file ' . $filename);

                if ($file_index === 0) {
                    $end = $first_file_limit_row === 0 ? $limit_row : $first_file_limit_row;
                } else {
                    $end = $start + $limit_row;
                }

                if (($end - count($this->products)) >= 0) {
                    $true = false;
                    $end = count($this->products);
                }

                // Друк шапки таблиці
                fputcsv($fileopen, array_keys($this->data_out[0]));
                // Друк боді таблиці
                for ($i = $start; $i < $end; $i++) {
                    fputcsv($fileopen, $this->data_out[$i]);
                }

                fclose($fileopen);
                $start = $end;
                $file_index++;
            }

        }

    }

}


