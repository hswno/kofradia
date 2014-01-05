<?php namespace Kofradia\Game\Rank;

/**
 * This trait is needed because of the use of late static bindings
 * so that the data will be stored in the child-class, not the
 * base class.
 *
 * If this property had been in the base class, all the child classes
 * would have the same data.
 */
trait BaseTrait {
	/**
	 * List by number (1 is lowest rank)
	 *
	 * @var array
	 */
	public static $by_number = array();
}