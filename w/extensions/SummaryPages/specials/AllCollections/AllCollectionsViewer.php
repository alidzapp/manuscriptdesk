<?php

/**
 * This file is part of the newManuscript extension
 * Copyright (C) 2015 Arent van Korlaar
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @package MediaWiki
 * @subpackage Extensions
 * @author Arent van Korlaar <akvankorlaar 'at' gmail 'dot' com> 
 * @copyright 2015 Arent van Korlaar
 */
class AllCollectionsViewer extends ManuscriptDeskBaseViewer implements SummaryPageViewerInterface {

    use HTMLLetterBar,
        HTMLJavascriptLoaderDots,
        HTMLPreviousNextPageLinks;

    private $page_name;

    public function __construct($out, $page_name) {
        parent::construct();
        $this->page_name = $page_name;
    }

    /**
     * This function shows the page after a request has been processed
     */
    public function showSingleLetterOrNumberPage(
    $alphabet_numbers, $uppercase_alphabet, $lowercase_alphabet, $button_name, array $page_titles, $offset, $next_offset) {

        $out = $this->out;
        $html = '';
        $html .= $this->getHTMLLetterBar($alphabet_numbers, $uppercase_alphabet, $lowercase_alphabet, $this->page_name);
        $html .= $this->getHTMLJavascriptLoaderDots();

        $html .= "<div class='javascripthide'>";

        $html .= $this->getHTMLPreviousNextPageLinks($out, $offset, $next_offset, $this->page_name, $button_name);

        $html .= "<form id='allcollections-post' action='" . $article_url . "Special:AllCollections' method='post'>";
        $html .= "<table id='userpage-table' style='width: 100%;'>";
        $html .= "<tr>";
        $html .= "<td class='td-three'>" . "<b>" . $out->msg('userpage-collection') . "</b>" . "</td>";
        $html .= "<td class='td-trhee'>" . "<b>" . $out->msg('userpage-user') . "</b>" . "</td>";
        $html .= "<td class='td-three'>" . "<b>" . $out->msg('userpage-creationdate') . "</b>" . "</td>";
        $html .= "</tr>";

        foreach ($page_titles as $single_collection_data) {

            $title = isset($single_collection_data['collections_title']) ? $single_collection_data['collections_title'] : '';
            $user = isset($single_collection_data['collections_user']) ? $single_collection_data['collections_user'] : '';
            $date = isset($single_collection_data['collections_date']) ? $single_collection_data['collections_date'] : '';

            $html .= "<tr>";
            $html .= "<td class='td-three'>";
            $html .= "<input type='submit' class='button-transparent' name='single_collection_posted' value='" . htmlspecialchars($title) . "'>";
            $html .= "</td>";
            $html .= "<td class='td-three'>" . htmlspecialchars($user) . "</td>";
            $html .= "<td class='td-three'>" . htmlspecialchars($date) . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";
        $html .= "</form>";
        $html .= "</div>";

        //this has to be added explicitly and not in the hook because somehow mPrefixedText does not work in this case
        $out->addModuleStyles('ext.userPage');

        return $out->addHTML($html);
    }

    /**
     * This function shows single collection data
     */
    public function showSingleCollectionData($selected_collection, $single_collection_data, $alphabet_numbers = array()) {

        global $wgArticleUrl;

        $out = $this->out;
        $article_url = $wgArticleUrl;
        list($meta_data, $pages_within_collection) = $single_collection_data;

        $out->setPageTitle($out->msg('allcollections'));

        $html .= $this->getHTMLLetterBar($alphabet_numbers, $uppercase_alphabet, $lowercase_alphabet, $this->page_name);
        $html .= $this->getHTMLJavascriptLoaderDots();

        $html .= "<div class='javascripthide'>";

        $html .= "<div id='userpage-singlecollectionwrap'>";

        $html .= "<h2 style='text-align: center;'>" . $out->msg('userpage-collection') . ": " . $selected_collection . "</h2>";
        $html .= "<br>";
        $html .= "<h3>" . $out->msg('userpage-metadata') . "</h3>";

        $meta_data = $this->HTMLSpecialCharachtersArray($meta_data);
        $html .= $collection_meta_table->getHTMLCollectionMetaTable($meta_data);

        $html .= "<h3>Pages</h3>";
        $html .= $out->msg('userpage-contains') . " " . count($pages_within_collection) . " " . $out->msg('userpage-contains2');
        $html .= "<br>";

        $html .= "<table id='userpage-table' style='width: 100%;'>";
        $html .= "<tr>";
        $html .= "<td class='td-long'>" . "<b>" . $out->msg('userpage-tabletitle') . "</b>" . "</td>";
        $html .= "<td>" . "<b>" . $out->msg('userpage-creationdate') . "</b>" . "</td>";
        $html .= "</tr>";

        foreach ($pages_within_collection as $key => $array) {

            $manuscripts_url = isset($array['manuscripts_url']) ? $array['manuscripts_url'] : '';
            $manuscripts_title = isset($array['manuscripts_title']) ? $array['manuscripts_title'] : '';
            $manuscripts_date = isset($array['manuscripts_date']) ? $array['manuscripts_date'] : '';

            $html .= "<tr>";
            $html .= "<td class='td-long'><a href='" . $article_url . htmlspecialchars($manuscripts_url) . "' title='" . htmlspecialchars($manuscripts_url) . "'>" .
                htmlspecialchars($manuscripts_title) . "</a></td>";
            $html .= "<td>" . htmlspecialchars($manuscripts_date) . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";
        $html .= "</div>";

        $html .= "</div>";
        $html .= "</div>";

        return $out->addHTML($html);
    }

    /**
     * This function shows the default page if no request was posted 
     */
    public function showDefaultPage($error_message, array $alphabet_numbers, array $uppercase_alphabet, array $lowercase_alphabet) {

        $out = $this->out;

        $out->setPageTitle($out->msg('allcollections'));

        $html .= $this->getHTMLLetterBar($alphabet_numbers, $uppercase_alphabet, $lowercase_alphabet);

        $html .= "<div class='javascripthide'>";

        $html .= $this->getHTMLJavascriptLoaderDots();


        if (!empty($error_message)) {
            $html .= "<br>";
            $html .= "<div class = 'error'>$error_message</div>";
        }

        $html .= "<p>" . $out->msg('allcollections-instruction') . "</p>";

        $html .= "</div>";

        return $out->addHTML($html);
    }

    public function showEmptyPageTitlesError(array $alphabet_numbers, array $uppercase_alphabet, array $lowercase_alphabet, $button_name) {

        $out = $this->out;
        $html = '';
        $html .= $this->getHTMLLetterBar($alphabet_numbers, $uppercase_alphabet, $lowercase_alphabet, $this->page_name, $button_name);
        $html .= $this->getHTMLJavascriptLoaderDots();

        $html .= "<div class='javascripthide'>";

        $out->setPageTitle($out->msg('allcollections'));

        if ($button_is_numeric) {
            $html .= "<p>" . $out->msg('allcollections-nocollections-number') . "</p>";
        }
        else {
            $html .= "<p>" . $out->msg('allcollections-nocollections') . "</p>";
        }

        $html .= "</div>";

        return $out->addHTML($html);
    }

}
