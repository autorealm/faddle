

STAMP
=====

[![Build Status](https://secure.travis-ci.org/gabordemooij/stamp.png)](http://travis-ci.org/gabordemooij/stamp)

Stamp is micro template library orignally written by Gabor de Mooij.

Stamp t.e. is a new kind of Template Engine for PHP. 
You don't need to learn a new 
template language and you get 100% separation 
between presentation logic and your HTML templates.

How it Works
------------

Stamp t.e. is a string 
manipulation based template engine. This is a different 
approach from most template engines 
which use inline templating. In Stamp t.e. 
you set markers in your template (HTML comments), 
these are then used to manipulate the template from the outside. 

What does it look like
----------------------


A cut point maker marks a region in the 
template that will be cut out from the template 
and stored under the specified ID. 

    <div>
    <!-- cut:diamond -->
    <img src="diamond.gif" />
    <!-- /cut:diamond -->
    </div>

    <span>
    <!-- paste:jewellery -->
    </span>

Now pass the template to StampTE:

    $se = new StampTE($templateHTML);

To obtain the diamond image:

    $diamond = $se->getDiamond();
    echo $diamond;

Result:


    <img src="diamond.gif" />

And.. to put some diamonds in the jewellery box:


    $se->jewellery->add($diamond);
    	

Easy!

More info: http://www.stampte.com


````

/**
 *  ---------------------------------------------------------------------------
 *  Example #1: Pizza prices, an introduction
 *  ---------------------------------------------------------------------------
 */

$t = '
<table>
<thead><tr><th>Pizza</th><th>Price</th></tr></thead>
<tbody>
<!-- cut:pizza -->
<tr><td>#name#</td><td>#price#</td></tr>
<!-- /cut:pizza -->
</tbody>
</table>
';

$data = array(
	'Magaritha' => '6.99',
	'Funghi' => '7.50',
	'Tonno' => '7.99'
);

$priceList = new StampTE( $t );

$dish = $priceList->getPizza(); 

foreach( $data as $name => $price ) {
	$pizza = $dish->copy(); 
	$pizza
		->setName( $name )
		->setPrice( $price );
	$priceList->add( $pizza ); 
}

echo $priceList;

/**
 *  ---------------------------------------------------------------------------
 *  Example #2: Building a form, the basics
 *  ---------------------------------------------------------------------------
 */

$t = '
<form action="#action#" method="post">
<!-- cut:textField -->
<label>#label#</label><input type="text" name="#name#" value="#value#" />
<!-- /cut:textField -->
</form>
';

$form = new StampTE( $t );

$textField = $form->getTextField();
$textField
	->setLabel( 'Your Name' )
	->setName( 'person' )
	->setValue( 'It\'s me!' );

$form->add( $textField );
echo "\n\n\n".$form;

/**
 *  ---------------------------------------------------------------------------
 *  Example #3: Game, multiple templates
 *  ---------------------------------------------------------------------------
 */

$vt = '<div id="forest"><div id="village"><!-- paste:village --></div></div>';
$bt = '
	<div class="catalogue">
		<!-- cut:tavern -->
		<img src="tavern.gif" />
		<!-- /cut:tavern -->
	</div>
';

$v = new StampTE( $vt );
$b = new StampTE( $bt );
$tavern = $b->getTavern();
$v->village->add( $tavern );

echo "\n\n\n".$v;
````

Advantages
----------

* Clean, code-free HTML templates, No PHP in your HTML
* Compact presentation logic free of any HTML
* No new syntax to learn, uses basic HTML markers already in use by many frontend developers to clarify document structure
* Templates do not have to be converted to be used with PHP logic (toll free template upgrades)
* Templates are presentable before integration because they may contain dummy data which is removed by StampTE
* Easy to exchange templates, templates are ready to use
* Very suitable for advanced UI development and complex templates for games
* Templates become self-documenting, PHP code becomes more readable (less bugs)
* Automatically strips HTML comments
* Integrated caching system
* Automatically escapes strings for Unicode (X)HTML documents
* Just ONE little file
* Unit tested, high quality code
* Open Source, BSD license



