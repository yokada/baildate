<?php
namespace baildate;

/*
$rules = [
  'plan_code'  => 'presence:true|inclusion:in(1,2,3)',
  'name'       => 'allow_blank:true|absence:true|length:min(1),max(10)|numericality:only_integer(true)',
  'email'      => 'presence:true|email',
  'amount'     => 'numerically:only_integer(true)',
  'card.id'   => '',
  'cards.#.id' => 'presence:true',
  'cards.#.name' => 'presence:true',
];
*/

class Validator implements ValidatorInterface {
  
  protected $params_origin;
  protected $params;
  protected $rules;
  protected $messages;
  protected $options;
  protected $errors;

  protected $custom_validators;

  public function __construct( $params = [], $rules = [], $messages = [], $options = [] ) {

    $this->params_origin   = $params;
    $this->params = $this->flatten_params($params);
    $this->rules    = $rules;
    $this->messages = $messages;
    $this->options  = $options;
    $this->custom_validators = [];
  }

  public function set_params( $params ) {
    $this->params_origin = $params;
    $this->params = $this->flatten_params($params);
  }

  public function set_rules( $rules ) {
    $this->rules = $rules;
  }

  public function get_messages() {
    return $this->messages;
  }

  public function set_messages( $messages ) {
    $this->messages = $messages;
  }

  public function get_errors() {
    return $this->errors;
  }

  public function set_options( $options ) {
    $this->options = $options;
  }

  public function add_customer_validator( Paywp_Validator_If $validator ) {
    $this->custom_validators[] = $validator;
  }

  /**
   * @return true|false
   */
  public function validate() {

    if (empty($this->rules)) {
      throw new Exception('Rules should not be empty.');
    }

    foreach ( $this->rules as $field => $rules ) {

      $rules = preg_replace('#\s#', '', $rules);
      $rules_list = explode('|', $rules);

      foreach($rules_list as $rule) {
        $result = $this->validate_rule($field, $rule);
        if ( is_array($result) ) {
          foreach ($result as $r => $err) {
            $this->errors[] = [$r => $err];
          }
          if ( $this->get_option('bail') ) {
            break;
          }
        }
      }
    }

    if ( ! empty($this->errors)) {
      return false;
    }

    return true;
  }

  public function get_option( $name ) {

    if (isset($this->options[$name])) {
      return $this->options[$name];
    }
  }

  /**
   * $field = 'card_num'   => $params['card_num']
   * $field = 'cards.id'   => $params['cards']['id']
   * $field = 'cards.#.id' => $params['cards'][#]['id']
   * $rule = 'presence:true'
   * $rule = 'numeric:only_integer,greater_than(10)'
   * @return true | [ [$filed => ValidationError], [$filed => ValidationError], ... ]
   */
  protected function validate_rule($field, $rule) {

    list($rule_name, $rule_options) = $this->parse_rule($rule);

    $rule_method = 'rule_' . $rule_name;
    if ( ! method_exists( $this, $rule_method ) ) {
      throw new Exception("Could not found validation rule method: {$rule}");
    }

    $errors = [];

    if ( strpos($field, '#') !== false ) {
      $field_regex = str_replace(['#', '.'], ['\d+', '\.'] , $field);
      foreach ($this->params as $k => $v) {
        if (preg_match("#{$field_regex}#", $k)) {
          $ret = $this->{$rule_method}($k, $v, $rule_options);
          if ($ret instanceof ValidationError) {
            $errors[$k] = $ret;
          }
        }
      }
    } else {
      $ret = $this->{$rule_method}($field, $this->get_value($field), $rule_options);
      if ($ret instanceof ValidationError) {
        $errors[$field] = $ret;
      }
    }

    return empty($errors) ? true : $errors;
  }

  /**
   * @param string $field_key 'cards.id', 'card_num', 'card.customer.name'
   * @return mixed|null
   */
  protected function get_value($field_key) {
    if ( isset( $this->params[$field_key] ) ){
      return $this->params[$field_key];
    }
  }

  protected function flatten_params($params) {

    $it = new \RecursiveIteratorIterator(
      new \RecursiveArrayIterator($params), \RecursiveIteratorIterator::SELF_FIRST);

    $params_flat = [];
    $f = [];
    $i = 0;
    foreach ($it as $k => $v) {

      $d = $it->getDepth();

      if ( $d == 0 ) {
        $f = [];
      } if ( $d < $i ) {
        array_pop( $f );
      }

      if (is_scalar($v) || is_null($v)) {
        $f[] = $k;      
        $params_flat[implode('.', $f)] = $v;
        array_pop($f);
      } elseif ( is_array($v) ) {
        $f[] = $k;
      }

      $i = $d;
    }

    return $params_flat;
  }

  protected function parse_rule_options($rule_options, ...$opt_names) {
    if (empty($rule_options)) {
      return;
    }
    $ret = [];
    foreach($opt_names as $name) {
      if (array_key_exists($name, $rule_options) && empty($rule_options[$name])) {
        $ret[$name] = true;
      } else {
        $ret[$name] = $rule_options[$name];
      }
    }

    return $ret;
  }

  /**
   * $rules = 'presence:true';
   * $rule_name: string(8) "presence"
   * $rule_options: array(1) {
   *   ["true"]=> NULL
   * }
   * 
   * $rules = 'inclusion_3:in(1,2,3),out(hoge,piyo),fuga';
   * $rule_name: string(11) "inclusion_3"
   * $rule_options: array(3) {
   *   ["in"]=>
   *   array(3) {
   *     [0]=>
   *     string(1) "1"
   *     [1]=>
   *     string(1) "2"
   *     [2]=>
   *     string(1) "3"
   *   }
   *   ["out"]=>
   *   array(2) {
   *     [0]=>
   *     string(4) "hoge"
   *     [1]=>
   *     string(4) "piyo"
   *   }
   *   ["fuga"]=>
   *   NULL
   * }
   */
  protected function parse_rule($rule) {

    $count = preg_match_all(
      '#
        (\w+)
        (?:
          \(
            ([\w,]+)
          \)
        )?
      #x', $rule, $m);

    if ( $count <= 0 ) {
      throw new Exception('Could not parse specified rule: ' . $rule);
    }

    $rule_name = array_shift($m[1]);array_shift($m[2]);
    $rule_options = [];

    foreach ($m[1] as $i => $v) {
      if (!empty($m[2][$i])) {
        $rule_options[$v] = explode(',', $m[2][$i]);
      } else {
        $rule_options[$v] = null;
      }
    }

    return [ $rule_name, $rule_options ];
  }

///////////////////////// Rules

  /**
   * @throws Exception
   * @return true|ValidationError
   */
  protected function rule_presence( $name, $value, $rule_options ) {
    return $this->_rule_presence($name, $value, true);
  }

  protected function rule_absence( $name, $value, $rule_options ) {
    return $this->_rule_presence($name, $value, false);
  }

  protected function _rule_presence( $name, $value, $rule_options) {

    if ( ! is_bool($rule_options ) ) {
      throw new Exception("{$name}: invalid rule options detected.");
    }

    if ( $opt === true && empty($value) ) {
      return new ValidationError("{$name} should be presence.");
    } elseif ( $opt === false && ! empty($value) ) {
      return new ValidationError("{$name} should be absence.");
    }
    return true;
  }

  protected function rule_not_null( $name, $value, $rule_options ) {
    if ( is_null($value) ) {
      return new ValidationError("{$name} should not be null.");
    }
    return true;
  }

  // 'numeric:only_integer,'
  protected function rule_numeric( $name, $value, $rule_options ) {
    list($only_integer, $greater_than, $greater_than_or_equal_to, $equal_to, $less_than, $less_than_or_equal_to, $odd, $even) = $this->parse_rule_options($rule_options, 'only_integer', 'greater_than', 'greater_than_or_equal_to', 'equal_to', 'less_than', 'less_than_or_equal_to', 'odd', 'even');
  }

  protected function rule_email( $name, $value, $rule_options ) {
    if ( ! filter_var($value, FILTER_VALIDATE_EMAIL) ){
      return new ValidationError("{$name} should be valid email address");
    }
    return true;
  }

  protected function rule_length( $name, $value, $rule_options ) {
    list($in, $min, $max, $is) = $this->parse_rule_options($rule_options, 'in', 'min', 'max', 'is');
  }

}