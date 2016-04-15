<?php

class butik_app extends APP_Controller{
	public $per_page = 60;
	
	public function init()
	{
		parent::init();
		$this->load("models", "Categories_model", true);
		$this->load("models", "Criteries_model", true);
		$this->load("models", "Products_model", true);
		$this->load("models", "Orders_model", true);
		$this->load("models", "Clients_model", true);
		$this->load("models", "Users_Model", true);
		
		$this->check_cookies();
		
		$cat_menu = $this->get_cat_menu();
		$this->to_template("cat_menu", $cat_menu);
		
		$this->set_tmp_order();
		
		$this->to_template("cart" , $this->parse_cart() );
		if (isset($_SESSION['access_user']))
			$this->to_template("me" , $_SESSION['access_user'] );
	}
	
	public function get_cat_menu()
	{
		$cats = $this->get_cat_by_parent(0);
		foreach($cats as &$main_cat)
		{
			$main_cat['childs'] = $this->get_cat_by_parent($main_cat['id']);
			foreach($main_cat['childs'] as &$sub_cat)
			{
				$sub_cat['childs'] = $this->get_cat_by_parent($sub_cat['id']);
			}
		}
		return $cats;
	}
		
	public function get_cat_by_parent($parent)
	{
		$cats = $this->categories_model->get_by_parent($parent);
		foreach($cats as $k => &$cat)
		{
			if ($this->is_multilang)
			{
				$title = json_decode($cat['title'], true);
				$cat['title'] = $title[LANG];
			}
			$cat['link'] = ADR . "/" . ($this->is_multilang ?  LANG . "/" : '') . "category/" . $cat['id'] . "_" . $this->get_cat_link($cat['title']);
			if ($cat['status'] != 1)
				unset($cats[$k]);
		}
		return $cats;
	}
	
	public function get_parents_init($parent)
	{
		$cat = $this->categories_model->get_by_id($parent);
		if (isset($cat['id']))
		{
			if ($this->is_multilang)
			{
				$cat['title'] = json_decode($cat['title'], true);
			}
			
			$final = array();
			$parent_cat = $this->get_parents_init($cat['parent']);
			if (count($parent_cat))
			{
				$final[] = $cat;
				$final = array_merge($parent_cat, $final);
				return $final;
			}
			else
				return array($cat);
		}
		else
			return array();
	}
	
	public function get_cat_link($title)
	{
		$arr = array(" ", "/", "'", '"', "?", "#", ":", ".");
		$title = str_replace($arr, "-", $title) . ".html";
		return $title;
	}
	
	public function find_cat()
	{
		if ($this->is_multilang)
		{
			$url = $this->request->location(3);
		}
		else
		{
			$url = $this->request->location(2);			
		}
		$tmp = explode("_", $url);
		$cat_id = $tmp[0];
		
		$cat = $this->categories_model->get_by_id($cat_id);
		if (!isset($cat['id']))
			return false;
		
		if ($this->is_multilang)
		{
			$title = json_decode($cat['title'], true);
			$cat['title'] = $title[LANG];
		}
		return $cat;
	}
	
	public function find_product()
	{
		if ($this->is_multilang)
		{
			$url = $this->request->location(3);
		}
		else
		{
			$url = $this->request->location(2);			
		}
		$tmp = explode("_", $url);
		$cat_id = $tmp[0];
		
		$product = $this->products_model->get_by_id($cat_id);
		if (!isset($product['id']))
			return false;
		
		$product['criteries'] = $this->get_product_criteries($product['criteries']);
		
		if ($this->is_multilang)
		{
			$title = json_decode($product['title'], true);
			$product['title'] = $title[LANG];
			$text = json_decode($product['text'], true);
			$product['text'] = $text[LANG];
		}
		$photo_path = ROOT .DS . "admin" . DS . "custom_fields" . DS . "photos" . DS . "custom_field_photos.php";
		require_once($photo_path);
		$photo_field = new custom_field_photos();
		$photo_field->set_config($this->config);
		$photo_field->init_db($this->db);
		$photo_field->init_template($this->template); 
		$photo_field->init();
		
		$product['photos'] = json_decode($product['photos']);
		foreach($product['photos'] as &$photo)
		{
			$arr = array("thumb" => $photo_field->view_photo($photo, array("w" => 200 , "h" => 200)), 
								 "large" => $photo_field->view_photo($photo, array("w" => 600 , "h" => 600)));
			$photo = $arr;
		}
		
		list($whole, $decimal) = explode('.', number_format($product['price_sell'], 2));
		$product['price_sell'] = $whole;
		$product['price_sell_decimal'] = $decimal;
		
		list($whole, $decimal) = explode('.', number_format($product['price_promo'], 2));
		$product['price_promo'] = $whole;
		$product['price_promo_decimal'] = $decimal;
		
		$product['link'] = ADR . "/" . ($this->is_multilang ?  LANG . "/" : '') . "products/" . $product['id'] . "_" . $this->get_cat_link($product['title']);
		
		return $product;
	}
	
	public function get_parents($parent_id)
	{
		$parents = $this->get_parents_init($parent_id);
		foreach($parents as &$cat)
		{
			if ($this->is_multilang)
			{
				$cat['title'] = $cat['title'][LANG];
			}
			$cat['link'] = ADR . "/" . ($this->is_multilang ?  LANG . "/" : '') . "category/" . $cat['id'] . "_" . $this->get_cat_link($cat['title']);
			if ($cat['status'] != 1)
				unset($cats[$k]);
		}
		return $parents;
	}
		
	public function get_products($parent)
	{
		$photo_path = ROOT .DS . "admin" . DS . "custom_fields" . DS . "photos" . DS . "custom_field_photos.php";
		require_once($photo_path);
		$photo_field = new custom_field_photos();
		$photo_field->set_config($this->config);
		$photo_field->init_db($this->db);
		$photo_field->init_template($this->template); 
		$photo_field->init();
		
		$page = $this->request->get("page");
		if (!$page)
			$page = 1;
		
		$products = $this->products_model->get_by_parents($parent , array(($page - 1 ) * $this->per_page, $this->per_page));
		foreach($products as &$product)
		{
			if ($this->is_multilang)
			{
				$title = json_decode($product['title'], true);
				$product['title'] = $title[LANG];
			}
			$product['photos'] = json_decode($product['photos'], true);
			if (is_array($product['photos']) && count($product['photos']))
				$product['main_photo'] = $photo_field->view_photo($product['photos'][0], array("w" => 400 , "h" => 400));
			
			list($whole, $decimal) = explode('.', number_format($product['price_sell'], 2));
			$product['price_sell'] = $whole;
			$product['price_sell_decimal'] = $decimal;
			
			list($whole, $decimal) = explode('.', number_format($product['price_promo'], 2));
			$product['price_promo'] = $whole;
			$product['price_promo_decimal'] = $decimal;
			
			$product['link'] = ADR . "/" . ($this->is_multilang ?  LANG . "/" : '') . "products/" . $product['id'] . "_" . $this->get_cat_link($product['title']);
		}
		
		$total_products = $this->products_model->count_by_parents($parent);
		$this->to_template("total_products", $total_products);
		
		$total_pages = ceil($total_products / $this->per_page);
		$this->to_template("total_pages", $total_pages);
		$this->to_template("current_page", $page);
		
		return $products;
	}
	
	public function get_criteries($ids)
	{
		$criteries = $this->criteries_model->get_by_parents($ids);
		foreach($criteries as $k=>&$crit)
		{
			if (!$crit['option_search'])
			{
				unset($criteries[$k]);
				continue;
			}
			
			if ($this->is_multilang)
			{
				$title = json_decode($crit['title'], true);
				$crit['title'] = $title[LANG];
			}
			
			$crit['values'] = $this->criteries_model->get_values($crit['id']);
			foreach($crit['values'] as &$val)
			{
				if ($this->is_multilang)
				{
					$value = json_decode($val['value'], true);
					$val['value'] = $value[LANG];
				}
			}
		}
		return $criteries;
	}
	
	public function get_product_criteries($criteries)
	{
		$final = array();
		$db_criteries = $this->criteries_model->get_by_ids(array_keys($criteries));
		foreach($db_criteries as $crit)
		{
			if ($crit['type'] > 0)
			{
				$v = $criteries[$crit['id']];
				$arr = array();
				$tmp = $this->criteries_model->get_critery_and_values($crit['id'] , $v);
				if (count($tmp))
				{
					if ($this->is_multilang)
					{
						$title = json_decode($tmp[0]['title'], true);
						$arr['title'] = $title[LANG];
					}
					else
						$arr['title'] = $tmp[0]['title'];
					
					$arr['values'] = array();
					foreach($tmp as $row)
					{
						if ($this->is_multilang)
						{
							$value = json_decode($row['value'], true);
							$arr['values'][] = $value[LANG];
						}
						else
							$arr['values'][] = $row['title'];
					}
				}
				$arr['values'] = implode(", ", $arr['values']);
			}
			else
			{
				if ($this->is_multilang)
				{
					$title = json_decode($crit['title'], true);
					$pre_value = json_decode($crit['value'], true);
					$arr['title'] = $title[LANG];
					$arr['values'] = $pre_value['pre'][LANG] . " " . $criteries[$crit['id']][0][LANG] . " " . $pre_value['post'][LANG];
				}
				else
				{
					$arr['title'] = $crit['title'];
					$pre_value = json_decode($crit['value'], true);
					$arr['values'] = $pre_value['pre'] . " " . $criteries[$crit['id']][0] . " " . $pre_value['post'];
				}
			}
			
			$final[] = $arr;
		}
		return $final;
	}
	
	private function set_tmp_order()
	{
		$order_tmp_id = isset($_SESSION['order_tmp_id']) ? $_SESSION['order_tmp_id'] : 0;
		if (!$order_tmp_id)
		{
			$order_tmp_id = isset($_COOKIE["order_tmp_id"]) ? $_COOKIE["order_tmp_id"] : 0;
			if ($order_tmp_id)
			{
				$tmp = $this->orders_model->get_tmp_by_id($order_tmp_id);
				$cart = unserialize($tmp['basket']);
				$_SESSION['cart'] = $cart;
			}
		}	
		if (!$order_tmp_id)
		{
			$order_tmp_id = $this->orders_model->create_tmp();
			$_SESSION['order_tmp_id'] = $order_tmp_id;
		
			setcookie("order_tmp_id", $order_tmp_id, time()+3600*365*5, "/");
			$_SESSION['cart'] = array("products" => array());
		}
	}
	
	public function parse_cart()
	{
		require_once(ROOT .DS . "admin" . DS . "controllers" . DS . "Custom_field.php");
		$photo_path = ROOT .DS . "admin" . DS . "custom_fields" . DS . "photos" . DS . "custom_field_photos.php";
		require_once($photo_path);
		$photo_field = new custom_field_photos();
		$photo_field->set_config($this->config);
		$photo_field->init_db($this->db);
		$photo_field->init_template($this->template); 
		$photo_field->init();
		
		$final = array("products"=>array());
		$cart = $_SESSION['cart'];
		$total = 0;
		$total_old = 0;
		$ids = array_keys($cart['products']);
		if (count($ids))
			$products = $this->products_model->get_by_ids($ids);
		else
			$products = array();
		foreach($products as $product)
		{
			if ($this->is_multilang)
			{
				$title = json_decode($product['title'], true);
				$product['title'] = $title[LANG];
			}
			
			$arr = array("title" => $product['title']);
			$arr['cant'] = $cart['products'][$product['id']]['cant'];
			$arr['id'] = $product['id'];
			$product['photos'] = json_decode($product['photos'], true);
			if (is_array($product['photos']) && count($product['photos']))
				$arr['photo'] = $photo_field->view_photo($product['photos'][0], array("w" => 50 , "h" => 50));
			
			if ($product['price_promo'])
				$arr['price'] = $product['price_promo'];
			else
				$arr['price'] = $product['price_sell'];
			
			$total_old += $product['price_sell'] * $arr['cant'];
			$total += $arr['price'] * $arr['cant'];
			
			$arr['total'] = $arr['price'] * $arr['cant'];
			list($whole, $decimal) = explode('.', number_format($arr['total'], 2));
			$arr['total'] = $whole;
			$arr['total_decimal'] = $decimal;
			
			list($whole, $decimal) = explode('.', number_format($arr['price'], 2));
			$arr['price'] = $whole;
			$arr['price_decimal'] = $decimal;
			
			$arr['link'] = ADR . "/" . ($this->is_multilang ?  LANG . "/" : '') . "products/" . $product['id'] . "_" . $this->get_cat_link($product['title']);
			$final['products'][$product['id']] = $arr;
		}
		
		list($whole, $decimal) = explode('.', number_format($total, 2));
		$final['total'] = $whole;
		$final['total_decimal'] = $decimal;
		
		list($whole, $decimal) = explode('.', number_format($total_old, 2));
		$final['total_old'] = $whole;
		$final['total_old_decimal'] = $decimal;
		
		return $final;
	}
	
	public function check_cookies()
	{
		$username = isset($_COOKIE['username']) ? $_COOKIE['username'] : "";
		$password = isset($_COOKIE['password']) ? $_COOKIE['password'] : "";
		
		if (strlen($username))
		{
			$client = $this->clients_model->search_by_phone($username);
			if (isset($client['id']))
			{
				if ($password == $client['password'])
				{
					setcookie("username", $client['phone'], time()+3600*365*5, "/");
					setcookie("password", $client['password'], time()+3600*365*5, "/");
					$_SESSION['access_user'] = $client;
				}
			}
		}
	}
}