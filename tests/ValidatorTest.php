<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace baildate;

class ValidatorTest extends \PHPUnit_Framework_TestCase
{
  function test_construct()
  {
    $v = new Validator();
    $this->assertTrue($v instanceof Validator);
  }

  /**
   * @dataProvider provider_parse_rule
   */
  function test_parse_rule($rules, $expected_rule_name, $expected_rule_options) {
    $validator = new Validator();
    list($rule_name, $rule_options) = $this->_invoke_private_method($validator, 'parse_rule', $rules);

    $this->assertEquals($rule_name, $expected_rule_name);
    $this->assertEquals($rule_options, $expected_rule_options);
  }

  function provider_parse_rule() {
    return [
      // #0
      [ 'presence:true', 'presence', [ 'true' => null ] ],

      // #1
      [ 'inclusion_3:in(1,2,3),out(hoge,piyo),fuga',
        'inclusion_3',
        [
          'in'  => [ '1', '2', '3'],
          'out' => [ 'hoge', 'piyo' ],
          'fuga' => null,
        ]
      ],
    ];
  }

  private function _invoke_private_method($instance, $method, $args) {
    $reflection = new \ReflectionClass($instance);
    $method = $reflection->getMethod($method);
    $method->setAccessible(true);
    return $method->invoke($instance, $args);
  }

}
