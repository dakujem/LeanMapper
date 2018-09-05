<?php

use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// counter of calls to EntityReflection::parseProperties
$parsingCalls = [];
LeanMapper\Reflection\EntityReflection::$onPropertyParse[] = function($reflection) use(&$parsingCalls) {
	$parsingCalls[$reflection->getName()] = 1 + ($parsingCalls[$reflection->getName()] ?? 0);
};
// counter of real property parsing
$realParsings = [];
LeanMapper\Reflection\EntityReflection::$onPropertyParse[] = function($reflection, array $line) use(&$realParsings) {
	// create an array of the ancestry line class names
	$classNames = array_map(function($v) {
		return $v->getName();
	}, $line);
	// for each clas name, the parsing is done,
	// see the EntityReflection::parseProperties method, the foreach( <ancestry> as $member){ <do_the_parsing> }
	foreach ($classNames as $name) {
		$realParsings[$name] = 1 + ($realParsings[$name] ?? 0);
	}
};

$reducer = function($carry, $val) {
	return $carry + $val;
};
// this will calculate the total number of calls to EntityReflection::parseProperties
$sum = function()use(&$parsingCalls, $reducer): int {
	return array_reduce($parsingCalls, $reducer, 0);
};
$sumReal = function()use(&$realParsings, $reducer): int {
	return array_reduce($realParsings, $reducer, 0);
};

// this will reset the counter
$reset = function()use(&$parsingCalls, &$realParsings): void {
	$parsingCalls = $realParsings = [];
};

// sanity test
Assert::same(0, count($parsingCalls));
Assert::same(0, $sum());
Assert::same(0, $sumReal());


/**
 * @property int $id
 * @property string $foo
 */
class Foo extends LeanMapper\Entity
{

}


/**
 * @property string $bar
 */
class Bar extends Foo
{

}


/**
 * @property int $unacceptable
 */
class Hell extends Bar
{

}


/**
 * @property int $unbearable
 */
class Exponential extends Hell
{

}

// First, let's create a new class, a descendant of the base Entity class
$foo = new Foo();

// the following test shows, that:
// - there is only one parsing being done
Assert::same(1, $sum(), 'The number of calls to parsing.');
Assert::same(1, $sumReal(), 'The real number of parsings done.');
$reset();


// Second, let's create a new class, a descendant of the Foo class
// the whole ancestry is: Bar -> Foo -> Entity
$bar = new Bar();

// the following test shows, that:
// - there is only 1 call to the parsing method
// - and 2 real parsings being done (Foo and Bar)
//
// The number of calls to parsing has doubled:
Assert::same(1, $sum());
// The real number of parsings done is even heigher:
Assert::same(2, $sumReal());
$reset();


// Third, let's create a descendant of the Bar class
// the whole ancestry is: Hell -> Bar -> Foo -> Entity
$hell = new Hell();
Assert::same(1, $sum());
Assert::same(3, $sumReal());
$reset();


// Finally, let's create a descendant of the Hell class
// the whole ancestry is: Exponential -> Hell -> Bar -> Foo -> Entity
$exp = new Exponential();

// The following test shows, that:
// - is again only 1 call to the parsing method for the one class we are creating
// - the number of parsings is equal to the depth of ancestry of the entity class
Assert::same(1, $sum());
Assert::same(4, $sumReal());
$reset();


// Last, but not least, check that there are no more parsing being done when creating the same Entities:
new Foo();
new Bar();
new Hell();
new Exponential();
Assert::same(0, $sum());
Assert::same(0, $sumReal());


// This test concludes that the number of property parsings is close to optimal.
// It could be further optimized for marginal gain.
