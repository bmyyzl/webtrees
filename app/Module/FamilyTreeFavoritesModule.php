<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2018 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Fisharebest\Webtrees\Module;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Theme;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use PDO;
use Ramsey\Uuid\Uuid;
use stdClass;

/**
 * Class FamilyTreeFavoritesModule
 *
 * Note that the user favorites module simply extends this module, so ensure that the
 * logic works for both.
 */
class FamilyTreeFavoritesModule extends AbstractModule implements ModuleBlockInterface {
	// How to update the database schema for this module
	const SCHEMA_TARGET_VERSION   = 4;
	const SCHEMA_SETTING_NAME     = 'FV_SCHEMA_VERSION';
	const SCHEMA_MIGRATION_PREFIX = '\Fisharebest\Webtrees\Module\FamilyTreeFavorites\Schema';

	/**
	 * Create a new module.
	 *
	 * @param string $directory Where is this module installed
	 */
	public function __construct($directory) {
		parent::__construct($directory);

		// Create/update the database tables.
		// NOTE: if we want to set any module-settings, we'll need to move this.
		Database::updateSchema(self::SCHEMA_MIGRATION_PREFIX, self::SCHEMA_SETTING_NAME, self::SCHEMA_TARGET_VERSION);
	}

	/**
	 * How should this module be labelled on tabs, menus, etc.?
	 *
	 * @return string
	 */
	public function getTitle() {
		return /* I18N: Name of a module */ I18N::translate('Favorites');
	}

	/**
	 * A sentence describing what this module does.
	 *
	 * @return string
	 */
	public function getDescription() {
		return /* I18N: Description of the “Favorites” module */ I18N::translate('Display and manage a family tree’s favorite pages.');
	}

	/**
	 * Generate the HTML content of this block.
	 *
	 * @param int      $block_id
	 * @param bool     $template
	 * @param string[] $cfg
	 *
	 * @return string
	 */
	public function getBlock($block_id, $template = true, $cfg = []): string {
		global $ctype, $WT_TREE;

		$action = Filter::get('action');
		switch ($action) {
			case 'deletefav':
				$favorite_id = Filter::getInteger('favorite_id');
				if ($favorite_id) {
					self::deleteFavorite($favorite_id);
				}
				break;
			case 'addfav':
				$gid      = Filter::get('gid', WT_REGEX_XREF, '');
				$favnote  = Filter::get('favnote');
				$url      = Filter::getUrl('url');
				$favtitle = Filter::get('favtitle');

				if ($gid !== '') {
					$record = GedcomRecord::getInstance($gid, $WT_TREE);
					if ($record && $record->canShow()) {
						self::addFavorite([
							'user_id'   => $ctype === 'user' ? Auth::id() : null,
							'gedcom_id' => $WT_TREE->getTreeId(),
							'gid'       => $record->getXref(),
							'type'      => $record::RECORD_TYPE,
							'url'       => null,
							'note'      => $favnote,
							'title'     => $favtitle,
						]);
					}
				} elseif ($url !== null) {
					self::addFavorite([
						'user_id'   => $ctype === 'user' ? Auth::id() : null,
						'gedcom_id' => $WT_TREE->getTreeId(),
						'gid'       => null,
						'type'      => 'URL',
						'url'       => $url,
						'note'      => $favnote,
						'title'     => $favtitle ? $favtitle : $url,
					]);
				}
				break;
		}

		$userfavs = $this->getFavorites($WT_TREE, Auth::user());

		$content = '';
		if ($userfavs) {
			foreach ($userfavs as $favorite) {
				$removeFavourite = '<a class="font9" href="index.php?ctype=' . $ctype . '&amp;ged=' . $WT_TREE->getNameHtml() . '&amp;action=deletefav&amp;favorite_id=' . $favorite->favorite_id . '" data-confirm="' . I18N::translate('Are you sure you want to remove this item from your list of favorites?') . '" onclick="return confirm(this.dataset.confirm);">' . I18N::translate('Remove') . '</a> ';
				if ($favorite->favorite_type === 'URL') {
					$content .= '<div id="boxurl' . e($favorite->favorite_id) . '.0" class="person_box">';
					if ($ctype == 'user' || Auth::isManager($WT_TREE)) {
						$content .= $removeFavourite;
					}
					$content .= '<a href="' . e($favorite->url) . '"><b>' . e($favorite->title) . '</b></a>';
					$content .= '<br>' . e((string) $favorite->note);
					$content .= '</div>';
				} else {
					$record = GedcomRecord::getInstance($favorite->xref, $WT_TREE);
					if ($record && $record->canShow()) {
						if ($record instanceof Individual) {
							$content .= '<div id="box' . e($favorite->xref) . '.0" class="person_box action_header';
							switch ($record->getSex()) {
								case 'M':
									break;
								case 'F':
									$content .= 'F';
									break;
								default:
									$content .= 'NN';
									break;
							}
							$content .= '">';
							if ($ctype == 'user' || Auth::isManager($WT_TREE)) {
								$content .= $removeFavourite;
							}
							$content .= Theme::theme()->individualBoxLarge($record);
							$content .= e((string) $favorite->note);
							$content .= '</div>';
						} else {
							$content .= '<div id="box' . e($favorite->xref) . '.0" class="person_box">';
							if ($ctype == 'user' || Auth::isManager($WT_TREE)) {
								$content .= $removeFavourite;
							}
							$content .= $record->formatList();
							$content .= '<br>' . e((string) $favorite->note);
							$content .= '</div>';
						}
					}
				}
			}
		}
		if ($ctype == 'user' || Auth::isManager($WT_TREE)) {
			$uniqueID = Uuid::uuid4(); // This block can theoretically appear multiple times, so use a unique ID.
			$content .= '<div class="add_fav_head">';
			$content .= '<a href="#" onclick="return expand_layer(\'add_fav' . $uniqueID . '\');">' . I18N::translate('Add a favorite') . '<i id="add_fav' . $uniqueID . '_img" class="icon-plus"></i></a>';
			$content .= '</div>';
			$content .= '<div id="add_fav' . $uniqueID . '" style="display: none;">';
			$content .= '<form name="addfavform" action="index.php">';
			$content .= '<input type="hidden" name="action" value="addfav">';
			$content .= '<input type="hidden" name="ctype" value="' . $ctype . '">';
			$content .= '<input type="hidden" name="ged" value="' . $WT_TREE->getNameHtml() . '">';
			$content .= '<div class="add_fav_ref">';
			$content .= '<input type="radio" name="fav_category" value="record" checked onclick="$(\'#gid' . $uniqueID . '\').removeAttr(\'disabled\'); $(\'#url, #favtitle\').attr(\'disabled\',\'disabled\').val(\'\');">';
			$content .= '<label for="gid' . $uniqueID . '">' . I18N::translate('Enter an individual, family, or source ID') . '</label>';
			$content .= '<input class="pedigree_form" data-autocomplete-type="IFSRO" type="text" name="gid" id="gid' . $uniqueID . '" size="5" value="">';
			$content .= '</div>';
			$content .= '<div class="add_fav_url">';
			$content .= '<input type="radio" name="fav_category" value="url" onclick="$(\'#url, #favtitle\').removeAttr(\'disabled\'); $(\'#gid' . $uniqueID . '\').attr(\'disabled\',\'disabled\').val(\'\');">';
			$content .= '<input type="text" name="url" id="url" size="20" value="" placeholder="' . GedcomTag::getLabel('URL') . '" disabled> ';
			$content .= '<input type="text" name="favtitle" id="favtitle" size="20" value="" placeholder="' . I18N::translate('Title') . '" disabled>';
			$content .= '<p>' . I18N::translate('Enter an optional note about this favorite') . '</p>';
			$content .= '<textarea name="favnote" rows="6" cols="50"></textarea>';
			$content .= '</div>';
			$content .= '<input type="submit" value="' . /* I18N: A button label. */ I18N::translate('add') . '">';
			$content .= '</form></div>';
		}

		$content = view('blocks/favorites', [
			'ctype'      => $ctype,
			'favorites'  => $userfavs,
			'is_manager' => Auth::isManager($WT_TREE),
			'tree'       => $WT_TREE,
		]);

		if ($template) {
			return view('blocks/template', [
				'block'      => str_replace('_', '-', $this->getName()),
				'id'         => $block_id,
				'config_url' => '',
				'title'      => $this->getTitle(),
				'content'    => $content,
			]);
		} else {
			return $content;
		}
	}

	/**
	 * Should this block load asynchronously using AJAX?
	 *
	 * Simple blocks are faster in-line, more comples ones
	 * can be loaded later.
	 *
	 * @return bool
	 */
	public function loadAjax(): bool {
		return false;
	}

	/**
	 * Can this block be shown on the user’s home page?
	 *
	 * @return bool
	 */
	public function isUserBlock(): bool {
		return false;
	}

	/**
	 * Can this block be shown on the tree’s home page?
	 *
	 * @return bool
	 */
	public function isGedcomBlock(): bool {
		return true;
	}

	/**
	 * An HTML form to edit block settings
	 *
	 * @param int $block_id
	 *
	 * @return void
	 */
	public function configureBlock($block_id) {
	}

	/**
	 * Delete a favorite from the database
	 *
	 * @param int $favorite_id
	 *
	 * @return bool
	 */
	public static function deleteFavorite($favorite_id) {
		return (bool)
			Database::prepare("DELETE FROM `##favorite` WHERE favorite_id=?")
			->execute([$favorite_id]);
	}

	/**
	 * Store a new favorite in the database
	 *
	 * @param $favorite
	 *
	 * @return bool
	 */
	public static function addFavorite($favorite) {
		// -- make sure a favorite is added
		if (empty($favorite['gid']) && empty($favorite['url'])) {
			return false;
		}

		//-- make sure this is not a duplicate entry
		$sql = "SELECT SQL_NO_CACHE 1 FROM `##favorite` WHERE";
		if (!empty($favorite['gid'])) {
			$sql .= " xref=?";
			$vars = [$favorite['gid']];
		} else {
			$sql .= " url=?";
			$vars = [$favorite['url']];
		}
		$sql .= " AND gedcom_id=?";
		$vars[] = $favorite['gedcom_id'];
		if ($favorite['user_id']) {
			$sql .= " AND user_id=?";
			$vars[] = $favorite['user_id'];
		} else {
			$sql .= " AND user_id IS NULL";
		}

		if (Database::prepare($sql)->execute($vars)->fetchOne()) {
			return false;
		}

		//-- add the favorite to the database
		return (bool)
			Database::prepare("INSERT INTO `##favorite` (user_id, gedcom_id, xref, favorite_type, url, title, note) VALUES (? ,? ,? ,? ,? ,? ,?)")
				->execute([$favorite['user_id'], $favorite['gedcom_id'], $favorite['gid'], $favorite['type'], $favorite['url'], $favorite['title'], $favorite['note']]);
	}

	/**
	 * Get favorites for a user or family tree
	 *
	 * @param Tree $tree
	 * @param User $user
	 *
	 * @return stdClass[]
	 */
	public static function getFavorites(Tree $tree, User $user) {
		$favorites =
			Database::prepare(
				"SELECT SQL_CACHE favorite_id, user_id, gedcom_id, xref, favorite_type, title, note, url" .
				" FROM `##favorite` WHERE gedcom_id = :tree_id AND user_id IS NULL")
			->execute([
				'tree_id' => $tree->getTreeId(),
			])
			->fetchAll();

		foreach ($favorites as $favorite) {
			$favorite->record = GedcomRecord::getInstance($favorite->xref, $tree);
		}

		return $favorites;
	}
}
