<?php
/**
 * Plugin Name: PerseoAdv
 * Plugin URI: https://wikiherbalist.com
 * Description: Plugin per inserire un box con prodotti Amazon nel contenuto dei singoli post
 * Version: 1.0.0
 * Author: Giovanni Manetti
 * Author URI: https://github.com/giovannimanetti11
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

 // Aggiunge stile .css

 function perseo_adv_enqueue_styles() {
    $css_url = plugin_dir_url(__FILE__) . 'style.css';

    wp_register_style('perseo-adv-styles', $css_url);
    wp_enqueue_style('perseo-adv-styles');
}

add_action('wp_enqueue_scripts', 'perseo_adv_enqueue_styles');


// Aggiunge file .js

function perseo_adv_enqueue__scripts() {
    wp_register_script('perseo_adv-functions', plugins_url('functions.js', __FILE__));
    wp_enqueue_script('perseo_adv-functions');
}

add_action('wp_enqueue_scripts', 'perseo_adv_enqueue__scripts');




function adv_products_debug_log($message) {
    if (WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}


 // Crea 3 custom field nei post e salva i dati inseriti

function adv_products_register_meta() {
    register_meta('post', 'AdvProduct1', array('show_in_rest' => true));
    register_meta('post', 'AdvProduct2', array('show_in_rest' => true));
    register_meta('post', 'AdvProduct3', array('show_in_rest' => true));
}

add_action('init', 'adv_products_register_meta');

function adv_products_add_meta_box() {
    add_meta_box(
        'adv_products_meta_box', 
        'Prodotti Amazon', 
        'adv_products_meta_box_callback', 
        'post', 
        'side', 
        'default' 
    );
}
add_action('add_meta_boxes', 'adv_products_add_meta_box');

function adv_products_meta_box_callback($post) {
    wp_nonce_field('adv_products_save_meta_box_data', 'adv_products_meta_box_nonce');

    
    for ($i = 1; $i <= 3; $i++) {
        $key = "AdvProduct" . $i;
        $value = get_post_meta($post->ID, $key, true);
        
        echo '<label for="' . $key . '">Prodotto Amazon ' . $i . ':</label>';
        echo '<input type="text" id="' . $key . '" name="' . $key . '" value="' . esc_attr($value) . '" size="25" />';
        echo '<br />';
    }
}


function adv_products_save_meta_box_data($post_id) {
    if (!isset($_POST['adv_products_meta_box_nonce'])) {
        // error_log('Nonce not set');
        return;
    }

    if (!wp_verify_nonce($_POST['adv_products_meta_box_nonce'], 'adv_products_save_meta_box_data')) {
        // error_log('Nonce verification failed');
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        // error_log('Autosave in progress');
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        // error_log('User does not have permission to edit this post');
        return;
    }

    for ($i = 1; $i <= 3; $i++) {
        $key = "AdvProduct" . $i;
        if (!isset($_POST[$key])) {
            // error_log($key . ' not found in $_POST');
            continue;
        }

        $value = $_POST[$key];
        $allowed_html = array(
            'iframe' => array(
                'src' => true,
                'width' => true,
                'height' => true,
                'frameborder' => true,
                'allowfullscreen' => true,
                'class' => true,
                'style' => true,
            ),
        );
        $value = wp_kses($value, $allowed_html);
        // error_log("Saving " . $key . " with value: " . $value);
        update_post_meta($post_id, $key, $value);
        // error_log("Saved " . $key . " with value: " . get_post_meta($post_id, $key, true));
    }
}


add_action('save_post', 'adv_products_save_meta_box_data');


// debug


function debug_custom_fields() {
    global $post;

    if (is_singular('post')) {
        $keys = ['AdvProduct1', 'AdvProduct2', 'AdvProduct3'];
        $custom_fields = [];

        foreach ($keys as $key) {
            $custom_fields[$key] = get_post_meta($post->ID, $key, true);
        }

        error_log(print_r($custom_fields, true));
    }
}

add_action('wp_footer', 'debug_custom_fields');


// Ottiene i dati del prodotto da Amazon

function adv_products_get_product_data($url) {

    // Se il link contiene un iframe, restituisci il link come tale
    if (strpos($url, '<iframe') !== false) {
        // Aggiungi una riga di debug per verificare se l'iframe viene rilevato correttamente
        adv_products_debug_log("Detected iframe: " . $url);

        return [
            'type' => 'iframe',
            'content' => $url,
        ];
    }

    // Carica file di configurazione con le chiavi Amazon
    require_once('config.php');
    $amazon_access_key = defined('AMAZON_ACCESS_KEY') ? AMAZON_ACCESS_KEY : '';
    $amazon_secret_key = defined('AMAZON_SECRET_KEY') ? AMAZON_SECRET_KEY : '';
    $amazon_associate_tag = defined('AMAZON_ASSOCIATE_ID') ? AMAZON_ASSOCIATE_ID : '';


    if (empty($amazon_access_key) || empty($amazon_secret_key)) {
        return null;
    }
    

    // Estrae l'ASIN (Amazon Standard Identification Number) dal link del prodotto
    preg_match('/dp\/([A-Z0-9]+)/i', $url, $matches);
    if (!isset($matches[1])) {
        return null;
    }
    $asin = $matches[1];

    // Prepara la richiesta firmata
    $endpoint = "webservices.amazon.it";
    $uri = "/onca/xml";
    $params = array(
        "Service" => "AWSECommerceService",
        "Operation" => "ItemLookup",
        "AWSAccessKeyId" => $amazon_access_key,
        "AssociateTag" => $amazon_associate_tag,
        "ItemId" => $asin,
        "IdType" => "ASIN",
        "ResponseGroup" => "Images,ItemAttributes,Offers",
        "Timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
    );

    ksort($params);
    $pairs = array();
    foreach ($params as $key => $value) {
        array_push($pairs, rawurlencode($key) . "=" . rawurlencode($value ?? ''));
    }
    
    $canonical_query_string = join("&", $pairs);
    $string_to_sign = "GET\n" . $endpoint . "\n" . $uri . "\n" . $canonical_query_string;
    $signature = base64_encode(hash_hmac("sha256", $string_to_sign, $amazon_secret_key, true));
    $request_url = 'https://' . $endpoint . $uri . '?' . $canonical_query_string . '&Signature=' . rawurlencode($signature);

    // Esegue la richiesta
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $request_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ));
    $response = curl_exec($curl);
    $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_status != 200) {
        error_log('Amazon API Error: HTTP status ' . $http_status . ' for ' . $url);
        return null;
    }

    // Estrae i dati del prodotto dalla risposta
    function is_valid_xml($content) {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        return empty($errors);
    }

    if (!is_valid_xml($response)) {
        return null;
    }
    
    
    $xml = simplexml_load_string($response);
    if ($xml instanceof SimpleXMLElement) {
        $namespaces = $xml->getNamespaces(true);
    } else {
        return null;
    }
    
    
    $namespaces = $xml->getNamespaces(true);

    if (!is_valid_xml($response)) {
        return null;
    }

    $item = $xml->Items->Item;
    $title = (string)$item->ItemAttributes->Title;
    $image_url = (string)$item->LargeImage->URL;
    $price = (string)$item->OfferSummary->LowestNewPrice->FormattedPrice;

    adv_products_debug_log("Product data for ASIN {$asin}: " . print_r($product_data, true));


    return [
        'title' => $title,
        'image' => $image_url,
        'price' => $price,
        'affiliate_link' => $url,
    ];
}





function add_associate_tag_to_amazon_url($url) {
    // Aggiunge il tag affiliato ai link inseriti nei custom field

    global $amazon_associate_tag;
    $amazon_associate_tag = defined('AMAZON_ASSOCIATE_ID') ? AMAZON_ASSOCIATE_ID : '';

    $parsed_url = parse_url($url);
    $query = array();
    parse_str($parsed_url['query'], $query);
    $query['tag'] = $amazon_associate_tag;
    $parsed_url['query'] = http_build_query($query);
    $new_url = http_build_url($parsed_url);
    return $new_url;
}

// Genera il markup HTML:
function adv_products_generate_html($product_data) {

    if ($product_data['type'] === 'iframe') {
        return $product_data['content'];
    }

    $affiliate_link = add_associate_tag_to_amazon_url($product_data['affiliate_link']); // Aggiungi l'Amazon Associate Tag all'URL del prodotto

    $output = '<div class="product-row">';
    $output .= '<div class="product-image"><img src="' . $product_data['image'] . '" alt="' . $product_data['title'] . '"></div>';
    $output .= '<div class="product-info">';
    $output .= '<div class="product-title">' . $product_data['title'] . '</div>';
    $output .= '<div class="product-price">' . $product_data['price'] . '</div>';
    $output .= '</div>';
    $output .= '<div class="product-cta"><a href="' . $affiliate_link . '" target="_blank">Compra ora</a></div>'; // Usa l'URL con il tag affiliato
    $output .= '</div>';

    return $output;
}



function adv_products_insert_into_content($content) {
    global $post;

    if (is_singular('post')) {
        $product_links = [];
        for ($i = 1; $i <= 3; $i++) {
            $key = "AdvProduct" . $i;
            $product_links[] = get_post_meta($post->ID, $key, true);
        }

        adv_products_debug_log("Product links: " . print_r($product_links, true));

        $output = '<div class="adv-products-container">';
        foreach ($product_links as $link) {
            $product_data = adv_products_get_product_data($link);
            adv_products_debug_log("Product data for {$link}: " . print_r($product_data, true));

            if ($product_data !== null) {
                $output .= adv_products_generate_html($product_data);
            }
        }
        $output .= '</div>';

        // Trova la posizione dell'H3 con testo "Sovradosaggio/Effetti indesiderati"
        $search_text = '<h3>Sovradosaggio/Effetti indesiderati</h3>';
        $position = strpos($content, $search_text);

        // Se l'H3 è stato trovato, inserisci il div .adv-products-container prima di esso
        if ($position !== false) {
            $content = substr_replace($content, $output, $position, 0);
        } else {
            // Altrimenti, aggiungi il contenuto alla fine di the_content
            $content .= $output;
        }
    }

    return $content;
}






add_filter('the_content', 'adv_products_insert_into_content');


