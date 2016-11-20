<?php namespace Faddle\Helper;

use InvalidParamError;
use DateTime;


/**
 * Feed 助手类
 */
class FeedHelper {

	private $title;
	private $link;
	private $description;
	private $language;

	private $author;

	private $items = array();

	/**
	 * Constructor
	 */
	function __construct($title, $link, $description = null, $language = null, $author = null) {
		$this->setTitle($title);
		$this->setLink($link);
		$this->setDescription($description);
		$this->setLanguage($language);
		$this->setAuthor($author);
	}

	/**
	 * Render in RSS 2.0 format
	*/
	function render() {
		$renderer = new FeedRendererRSS2();
		return $renderer->render($this);
	}

	// ---------------------------------------------------
	//  Getters and setters
	// ---------------------------------------------------

	function getTitle() {
		return $this->title;
	}

	function setTitle($value) {
		$this->title = $value;
	}

	function getLink() {
		return $this->link;
	}

	function setLink($value) {
		$this->link = $value;
	}

	function getDescription() {
		return $this->description;
	}

	function setDescription($value) {
		$this->description = $value;
	}

	function getLanguage() {
		return $this->language;
	}

	function setLanguage($value) {
		$this->language = $value;
	}

	function getAuthor() {
		return $this->author;
	}

	/**
	* Set author value
	*
	* @param Angie_Feed_Author $value
	* @return null
	*/
	function setAuthor($name, $email, $link = null) {
		if (!is_null($email) && !is_valid_email($email)) {
			throw new InvalidParamError('email', $email, "$email is not a valid email address");
		}
		if (!is_null($link) && !is_valid_url($link)) {
			throw new InvalidParamError('link', $link, "$link is not a valid URL");
		}
		$author = array();
		$author['name'] = $name;
		$author['email'] = $email;
		$author['link'] = $link;
		$this->author = $author;
	}

	function getItems() {
		return $this->items;
	}

	/**
	 * Add item to feed
	 */
	function addItem($title, $link, $description, DateTime $publication_date) {
		$item = array();
		$item['title'] = $title;
		$item['link'] = $link;
		$item['description'] = $description;
		$item['publication_date'] = $publication_date;
		$this->items[] = $item;
		return $item;
	}

}


/**
 * RSS 2.0 feed renderer
 * 
 * This renderer will use input feed object to render valid RSS 2.0 feed
 */
class FeedRendererRSS2 {

	function render($feed) {
		$result  = "<rss version=\"2.0\">\n";
		$result .= "<channel>\n";
		$feed_url = ($feed->getLink());
		$result .= '<title>' . htmlspecialchars($feed->getTitle()) . "</title>\n";
		$result .= '<link>' . $feed_url . "</link>\n";
		if ($description = trim($feed->getDescription())) {
			$description = "empty";
		}
		$result .= '<description>' . htmlspecialchars($description) . "</description>\n";
		if ($language = trim($feed->getLanguage())) {
			$result .= '<language>' . ($language) . "</language>\n";
		}
		
		foreach ($feed->getItems() as $feed_item) {
			$result .= $this->renderItem($feed_item) . "\n";
		}
		$author = $feed->getAuthor();
		if (! empty($author)) {
			$result .= '<author>' . trim($author['email']) . ' (' . trim($author['name']) . ")</author>\n";
		}
		
		$result .= "</channel>\n</rss>";
		return $result;
	}

	private function renderItem($item) {
		$result  = "<item>\n";
		$result .= '<title>' . htmlspecialchars($item['title']) . "</title>\n";
		$link = ($item[link]);
		$result .= '<link>' . $link . "</link>\n";
		if ($description = trim($item['description'])) {
			$description = "empty";
		}
		$result .= '<description>' . htmlspecialchars($description) . "</description>\n";
		
		$timestamp = NULL;
		$pubdate = $item['publication_date'];
		if ($pubdate instanceof DateTime) {
			$result .= '<pubDate>' . $pubdate->format(DateTime::RSS) . "</pubDate>\n";
			$timestamp = $pubdate->getTimestamp();
		}
		$result .= '<guid>' . $this->buildGuid(($item['link']), $timestamp) . "</guid>\n";
		
		$result .= '</item>';
		return $result;
	}

	private function buildGuid($url, $timestamp) {
		$url = preg_replace('/&amp;\d*&amp;/', '&amp;', $url); // remove non-constant parameter
		if (!is_null($timestamp)) $url .= "&amp;time_id=" . $timestamp;
		return $url;
	}

}
