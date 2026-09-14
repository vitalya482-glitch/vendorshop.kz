<?php
// VENDSHOP_CALC: controller (точка входа страницы калькулятора АКБ)

class ControllerInformationCalculator extends Controller {
    public function index() {
        $this->load->language('information/calculator');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->setDescription($this->language->get('meta_description'));
        $this->document->setKeywords($this->language->get('meta_keywords'));

        // VENDSHOP_CALC_STAGE7: подключаем JS калькулятора
        $this->document->addScript('catalog/view/javascript/calculator-akb.js?v=' . time());

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('information/calculator')
        );

        $keys = array(
            'heading_title','text_description','text_calc_title','text_result_title','text_formula_title','text_formula_body',
            'text_seo_title','text_seo_body','text_request_title','text_request_body','button_request','button_calculate',
            'label_load_power','label_backup_time','label_system_voltage','label_battery_type','label_efficiency',
            'label_reserve_percent','label_battery_ah','label_battery_ah_default','label_custom_battery_ah','label_max_load_power',
            'label_power_unit','label_calc_mode','label_required_capacity','label_custom_series','label_lifepo4_variant','label_battery_assembly_ah',
            'text_min_capacity','text_config','text_battery_count','text_energy','text_recommendation','text_pack_format',
            'text_pack_badge','text_total_batteries_short','text_energy_kwh','text_recommended_capacity_note','text_actual_capacity_note','text_required_capacity_calc_note',
            'text_power_unit_w','text_power_unit_kw','text_select_capacity','text_manual_capacity','text_loading_values',
            'text_no_capacity_values','text_failed_load_values','text_result_placeholder','text_matching_cells_title',
            'text_matching_cells_note','text_matching_bms_title','text_matching_bms_note','text_select_cell','text_selected_cell',
            'text_no_cells_found','text_no_bms_found','text_open_product','text_price_cell_unit','text_price_cell_qty',
            'text_price_cell_total','text_price_bms_unit','text_price_bms_qty','text_price_bms_total','text_total_cost',
            'text_cost_summary_title','text_price_not_available','text_select_bms','text_selected_bms','button_order_project',
            'text_calc_mode_ups','text_calc_mode_custom','text_system_voltage_custom','text_custom_voltage_help',
            'text_current_not_set','text_order_project_success','text_order_project_error','text_lifepo4_variant_cells','text_lifepo4_variant_battery','text_battery_ready_note' 
        );

        foreach ($keys as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['request_email'] = 'ka_vendor@mail.ru';
        $data['calculator_data_url'] = $this->url->link('information/calculator_data/batteryOptions');
        $data['calculator_order_url'] = $this->url->link('information/calculator_data/createProjectOrder');
        $data['cart_url'] = $this->url->link('checkout/cart');
        $data['checkout_url'] = $this->url->link('checkout/checkout', '', true);

        // VENDSHOP_CALC: загрузка товаров для подбора
        $products_data = $this->getCalculatorProducts();
        $data['calculator_cells_json'] = json_encode($products_data['cells'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data['calculator_bms_json'] = json_encode($products_data['bms'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data['calculator_batteries_json'] = json_encode($products_data['batteries'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data['calculator_currency_json'] = json_encode($this->getCurrencyMeta(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('information/calculator', $data));
    }

    private function getCalculatorProducts() {
        $results = array(
            'cells' => array(),
            'bms' => array(),
            'batteries' => array()
        );

        $filter_data = array(
            'sort' => 'p.sort_order',
            'order' => 'ASC',
            'start' => 0,
            'limit' => 1000
        );

        $products = $this->model_catalog_product->getProducts($filter_data);

        foreach ($products as $product) {
            $attributes = $this->model_catalog_product->getProductAttributes($product['product_id']);
            $map = $this->buildAttributeMap($attributes);

            $calc_type = isset($map['тип для калькулятора']) ? $map['тип для калькулятора'] : '';
            $chemistry = isset($map['химия акб']) ? $map['химия акб'] : '';

            if ($calc_type !== 'battery' && $chemistry !== 'lifepo4') {
                continue;
            }

            $product_payload = array(
                'product_id' => (int)$product['product_id'],
                'name' => $product['name'],
                'href' => $this->url->link('product/product', 'product_id=' . (int)$product['product_id']),
                'price' => $this->toFloat($product['price']),
                'image' => !empty($product['image']) ? $this->model_tool_image->resize($product['image'], 96, 96) : ''
            );

            if ($calc_type === 'cell') {
                $voltage = $this->toFloat(isset($map['напряжение, в']) ? $map['напряжение, в'] : 0);
                $capacity_ah = $this->toFloat(isset($map['ёмкость, ач']) ? $map['ёмкость, ач'] : 0);

                if ($voltage > 0 && $capacity_ah > 0) {
                    $product_payload['type'] = 'cell';
                    $product_payload['chemistry'] = 'LiFePO4';
                    $product_payload['voltage'] = $voltage;
                    $product_payload['capacity_ah'] = $capacity_ah;

                    $results['cells'][] = $product_payload;
                }
            }


            if ($calc_type === 'battery') {
                $voltage = $this->toFloat(isset($map['напряжение, в']) ? $map['напряжение, в'] : (isset($map['напряжение']) ? $map['напряжение'] : 0));
                $capacity_ah = $this->toFloat(isset($map['ёмкость, ач']) ? $map['ёмкость, ач'] : (isset($map['емкость, ач']) ? $map['емкость, ач'] : 0));

                if ($capacity_ah > 0) {
                    $product_payload['type'] = 'battery';
                    $product_payload['chemistry'] = $chemistry !== '' ? 'LiFePO4' : '';
                    $product_payload['voltage'] = $voltage;
                    $product_payload['capacity_ah'] = $capacity_ah;

                    $results['batteries'][] = $product_payload;
                }
            }

            if ($calc_type === 'bms') {
                $min_s = (int)$this->toFloat(isset($map['мин. ячеек s']) ? $map['мин. ячеек s'] : 0);
                $max_s = (int)$this->toFloat(isset($map['макс. ячеек s']) ? $map['макс. ячеек s'] : 0);
                $max_current_a = $this->toFloat(isset($map['максимальный ток, а']) ? $map['максимальный ток, а'] : 0);

                if ($min_s > 0 && $max_s > 0) {
                    $product_payload['type'] = 'bms';
                    $product_payload['chemistry'] = 'LiFePO4';
                    $product_payload['min_s'] = $min_s;
                    $product_payload['max_s'] = $max_s;
                    $product_payload['max_current_a'] = $max_current_a;

                    $results['bms'][] = $product_payload;
                }
            }
        }

        return $results;
    }

    private function buildAttributeMap($attribute_groups) {
        $map = array();

        foreach ($attribute_groups as $attribute_group) {
            if (!empty($attribute_group['attribute'])) {
                foreach ($attribute_group['attribute'] as $attribute) {
                    $name = $this->normalizeAttributeKey($attribute['name']);
                    $text = trim(html_entity_decode($attribute['text'], ENT_QUOTES, 'UTF-8'));

                    if ($name !== '') {
                        $map[$name] = $this->normalizeAttributeValue($text);
                    }
                }
            }
        }

        return $map;
    }

    private function normalizeAttributeKey($value) {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/\s+/', ' ', $value);
        return $value;
    }

    private function normalizeAttributeValue($value) {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = str_replace(',', '.', $value);
        return $value;
    }

    private function toFloat($value) {
        return (float)str_replace(',', '.', (string)$value);
    }

    private function getCurrencyMeta() {
        $currency_code = !empty($this->session->data['currency']) ? $this->session->data['currency'] : $this->config->get('config_currency');

        return array(
            'code' => $currency_code,
            'symbol_left' => $this->currency->getSymbolLeft($currency_code),
            'symbol_right' => $this->currency->getSymbolRight($currency_code),
            'decimal_place' => (int)$this->currency->getDecimalPlace($currency_code),
            'value' => (float)$this->currency->getValue($currency_code)
        );
    }
}
