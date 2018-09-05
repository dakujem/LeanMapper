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
// - there are 2 calls made to the parsing method (one for Foo, one for Entity - because parseProperties is called in constructor)
// - the number of actual parsings done is the same as the number of calls (which is "okay")
Assert::same(2, $sum(), 'The number of calls to parsing.');
Assert::same(2, $sumReal(), 'The real number of parsings done.');
$reset();


// Second, let's create a new class, a descendant of the Foo class
// the whole ancestry is: Bar -> Foo -> Entity
$bar = new Bar();

// the following test shows, that:
// - there are 4 calls now made to the parsing method (one for Bar, one for Foo and two (!) for Entity), explained later
// - there are 5 real parsings done now (one for Bar, two (!) for Foo and two for Entity)
//
// The number of calls to parsing has doubled:
Assert::same(4, $sum());
// The real number of parsings done is even heigher:
Assert::same(5, $sumReal());
$reset();


// Third, let's create a descendant of the Bar class
// the whole ancestry is: Hell -> Bar -> Foo -> Entity
$hell = new Hell();

// The number of calls to parsing has doubled again:
Assert::same(8, $sum());
// You need to understand that for each property type, there\'s this many REGEXP calls, that makes it 22... to create a single entity:
Assert::same(11, $sumReal());
$reset();


// Finally, let's create a descendant of the Hell class
// the whole ancestry is: Exponential -> Hell -> Bar -> Foo -> Entity
$exp = new Exponential();

// The following test shows, that:
// - there are 16 calls made to the parsing method (1 for Exp, 1 for Hell, 2 for Bar, 4 for Foo and 8 for Entity)
// - there are 23 real parsings done now (1 for Exp, 2 for Hell, 4 for Bar, 8 for Foo and 8 for Entity)
// - the number grows EXPONENTIALLY, 2^N
Assert::same(16, $sum(), '... and again.');
Assert::same(23, $sumReal(), 'The number of calls to parsing.');
$reset();


// This may seem unimportant, but one can easily have this long ancestry:
// - the project uses an extension of LeanMapper, that provides a base class
// - the project itself might have a base entity class
// - the project may be divided into modules and for each module they may be a base class, whyever not
// - the final models might be groupped together using common methods / tables whatever
//
// Then, big projects have a hundred models. For each request, hundreds of REGEXP calls are wasted this way.

