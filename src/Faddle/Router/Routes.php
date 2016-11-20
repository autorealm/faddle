<?php namespace Faddle\Router;

/**
 * 路由集
 */
class Routes extends \SplObjectStorage {

	/**
	 * 添加一个路由到集合中
	 *
	 * @param Route $attachObject
	 */
	public function add(Route $attachObject) {
		parent::attach($attachObject, null);
	}

	/**
	 * 取出所有路由集合中的路由并返回数组形式
	 *
	 * @return Route[]
	 */
	public function all() {
		$temp = array();
		foreach ($this as $route) {
			$temp[] = $route;
		}
		return $temp;
	}

}