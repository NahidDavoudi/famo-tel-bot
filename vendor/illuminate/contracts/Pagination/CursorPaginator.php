<?php

namespace Illuminate\Contracts\Pagination;

/**
 * @template TKey of array-key
 *
 * @template-covariant TValue
 *
 * @method $this through(callable(TValue): mixed $callback)
 */
interface CursorPaginator
{
    /**
     * Get the URL for a given cursor.
     *
     * @param  \Illuminate\Pagination\Cursor|null  $cursor
     *  string
     */
    public function url($cursor);

    /**
     * Add a set of query string values to the paginator.
     *
     * @param  array|string|null  $key
     * @param  string|null  $value
     *  $this
     */
    public function appends($key, $value = null);

    /**
     * Get / set the URL fragment to be appended to URLs.
     *
     * @param  string|null  $fragment
     *  ($fragment is null ? string|null : $this)
     */
    public function fragment($fragment = null);

    /**
     * Add all current query string values to the paginator.
     *
     *  $this
     */
    public function withQueryString();

    /**
     * Get the URL for the previous page, or null.
     *
     *  string|null
     */
    public function previousPageUrl();

    /**
     * The URL for the next page, or null.
     *
     *  string|null
     */
    public function nextPageUrl();

    /**
     * Get all of the items being paginated.
     *
     *  array<TKey, TValue>
     */
    public function items();

    /**
     * Get the "cursor" of the previous set of items.
     *
     *  \Illuminate\Pagination\Cursor|null
     */
    public function previousCursor();

    /**
     * Get the "cursor" of the next set of items.
     *
     *  \Illuminate\Pagination\Cursor|null
     */
    public function nextCursor();

    /**
     * Determine how many items are being shown per page.
     *
     *  int
     */
    public function perPage();

    /**
     * Get the current cursor being paginated.
     *
     *  \Illuminate\Pagination\Cursor|null
     */
    public function cursor();

    /**
     * Determine if there are enough items to split into multiple pages.
     *
     *  bool
     */
    public function hasPages();

    /**
     * Determine if there are more items in the data source.
     *
     *  bool
     */
    public function hasMorePages();

    /**
     * Get the base path for paginator generated URLs.
     *
     *  string|null
     */
    public function path();

    /**
     * Determine if the list of items is empty or not.
     *
     *  bool
     */
    public function isEmpty();

    /**
     * Determine if the list of items is not empty.
     *
     *  bool
     */
    public function isNotEmpty();

    /**
     * Render the paginator using a given view.
     *
     * @param  string|null  $view
     * @param  array  $data
     *  string
     */
    public function render($view = null, $data = []);
}
