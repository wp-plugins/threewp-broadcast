<?php
/**
 * Data handling class for the post data object.
 * 
 * Which is basically only an array which we treat special.
 */
class BroadcastData
{
	private $defaultData = array(
		'version' => 1,
	);
	private $dataModified = false;
	private $data;
	
	/**
	 * Create the class with the specified array as the data.
	 */
	public function BroadcastData($data)
	{
		$this->data = array_merge($this->defaultData, $data);
	}
	
	/**
	 * Returns the data array.
	 */
	public function getData()
	{
		return $this->data;
	}
	
	/**
	 * Does this post have any linked children?
	 */
	public function has_linked_children()
	{
		return isset($this->data[ 'linked_children' ]);
	}
	
	/**
	 * Does this post have children on the current blog(
	 * 
	 * Used after switch_to_blog has been called.
	 */
	public function has_linked_child_on_this_blog( $blog__id = null )
	{
		global $blog_id;
		if ( $blog__id === null )
			$blog__id = $blog_id;
		return isset( $this->data[ 'linked_children' ][ $blog__id ] );
	}
	
	/**
	 * Return the post_id of the child post on the current blog.
	 */
	public function get_linked_child_on_this_blog()
	{
		global $blog_id;
		if ( $this->has_linked_child_on_this_blog() )
			return $this->data[ 'linked_children' ][$blog_id];
		else
			return null;
	}
	
	/**
	 * Returns an array of all the linked children.
	 * 
	 * [blog_id] => [post_id]
	 */
	public function get_linked_children()
	{
		if (!$this->has_linked_children())
			return array();
		
		return $this->data[ 'linked_children' ];
	}
	
	/**
	 * Adds a linked child for this post.
	 * @param int $blog_id Blog ID
	 * @param int $post_id Post ID of child post
	 */
	public function add_linked_child( $blog_id, $post_id )
	{
		$this->data[ 'linked_children' ][$blog_id] = $post_id;
		$this->modified();
	}
	
	/**
	 * Removes a child from a blog.
	 */
	public function remove_linked_child( $blog_id )
	{
		unset($this->data[ 'linked_children' ][$blog_id]);
		if ( count($this->data[ 'linked_children' ]) < 1)
			unset( $this->data[ 'linked_children' ] );
		
		$this->modified();
	}
	
	/**
	 * Clears all the linked children.
	 */
	public function remove_linked_children()
	{
		unset($this->data[ 'linked_children' ]);
		$this->modified();
	}
	
	/**
	 * Remove linked parent
	 */
	public function get_linked_parent()
	{
		if (isset($this->data[ 'linked_parent' ]))
			return $this->data[ 'linked_parent' ];
		else
			return false;
	}

	/**
	 * Sets the parent post of this post.
	 * @param int $blog_id Blog ID
	 * @param int $post_id Post ID of child post
	 */
	public function set_linked_parent( $blog_id, $post_id )
	{
		$this->data[ 'linked_parent' ] = array('blog_id' => $blog_id, 'post_id' => $post_id);
		$this->modified();
	}

	/**
	 * Remove linked parent
	 */
	public function remove_linked_parent()
	{
		unset($this->data[ 'linked_parent' ]);
		$this->modified();
	}

	/**
	 * Flags the data as "modified".
	 */
	private function modified()
	{
		$this->dataModified = true;
	}
	
	/**
	 * Returns whether this broadcast data has been modified and needs to be saved.
	 */
	public function is_modified()
	{
		return $this->dataModified;
	}
	
	/**
	 * Returns whether the only data contained is worthless default data
	 */
	public function is_empty()
	{
		return (
			(count($this->data) == 1)
			&&
			( isset($this->data[ 'version' ]) )
		);
	}
}
?>