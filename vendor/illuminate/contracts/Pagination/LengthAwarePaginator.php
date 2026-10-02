<?php

namespace Illuminate\Contracts\Pagination;

/**
 * @template TKey of array-key
 *
 * @template-covariant TValue
 *
 * @extends Paginator<TKey, TValue>
 */
interface LengthAwarePaginator extends Paginator
{
    /**
     * Create a range of pagination URLs.
     *
     * @param  int  $start
     * @param  int  $end
     *  array
     */
    public function getUrlRange($start, $end);

    /**
     * Determine the total number of items in the data store.
     *
     *  int
     */
    public function total();

    /**
     * Get the page number of the last available page.
     *
     *  int
     */
    public function lastPage();
}
