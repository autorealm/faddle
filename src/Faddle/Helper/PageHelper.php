<?php namespace Faddle\Helper;

class PageHelper {

	/**
	 * 
	 */
	public function __construct() {
		
	}

	/**
	 * Get value
	 */
	public function get($key) {
		
	}

	/**
	 * Set value
	 */
	public function set($key, $value) {
		
	}


	/**
	 * 分页函数
	 *
	 * @param $total_num 信息总数
	 * @param $cur_page 当前分页
	 * @param $page_size 每页显示数
	 * @param $page_set 显示页数
	 * @return 分页
	 */
	public static function paginate($cur_page, $total_num, $page_set = 10, $page_size = 20) {
		$multipage = array();
		
		$offset = ceil($page_set / 2 - 1);
		$pages = ceil($total_num / $page_size);
		
		$multipage['curpage'] = $cur_page;
		$multipage['pagesize'] = $page_size;
		$multipage['totalpage'] = $pages;
		$multipage['totalsize'] = $total_num;
		$multipage['offset'] = $offset;
		$multipage['from'] = 1;
		$multipage['to'] = 1;
		$multipage['more'] = 0;
		$multipage['previous'] = 0;
		$multipage['next'] = 0;
		$multipage['frontmore'] = 0;
		$multipage['endmore'] = 0;
		
		if ($total_num <= $page_size)
			return $multipage;
		
		$from = $cur_page - $offset;
		$to = $cur_page + $page_set - $offset - 1;
		
		$more = 0;
		if ($page_set > $pages) {
			$from = 1;
			$to = $pages;
		} else {
			if ($from < 1) {
				$to = $cur_page + 1 - $from;
				$from = 1;
				if (($to - $from) < $page_set && ($to - $from) < $pages) {
					$to = $page_set;
				}
			} elseif ($to > $pages) {
				$from = $cur_page - $pages + $to;
				$to = $pages;
				if (($to - $from) < $page_set && ($to - $from) < $pages) {
					$from = $pages - $page_set + 1;
				}
			}
			$more = 1;
		}
		
		$multipage['from'] = $from;
		$multipage['to'] = $to;
		$multipage['more'] = $more;
		
		if ($cur_page > 1) {
			$multipage['previous'] = 1;
		}
		
		if ($from > 1 && $more) {
			$multipage['frontmore'] = 1;
		}
		
		for ($i = $from; $i <= $to; $i++) {
			if ($i != $cur_page) {
				$multipage['pages'][$i] = $i;
			} else {
				$multipage['pages'][$i] = '';
			}
		}
		
		if ($cur_page < $pages) {
			if ($to < $pages && $more) {
				$multipage['endmore'] = 1;
			}
			$multipage['next'] = 1;
		}
		
		return $multipage;
	}


}
