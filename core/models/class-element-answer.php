<?php

/**
 * Element answer class
 *
 * @author  awesome.ug <contact@awesome.ug>
 * @package TorroForms
 * @version 1.0.0alpha1
 * @since   1.0.0
 * @license GPL 2
 *
 * Copyright 2015 rheinschmiede (contact@awesome.ug)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Torro_Element_Answer extends Torro_Instance_Base {

	protected $label = null;

	protected $sort = null;

	protected $section = '';

	public function __construct( $id = null ) {
		$this->superior_id_name = 'element_id';
		$this->manager_method = 'element_answers';
		$this->valid_args = array( 'label', 'sort', 'section' );

		parent::__construct( $id );
	}

	protected function populate( $id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->torro_element_answers} WHERE id = %d", $id );

		$answer = $wpdb->get_row( $sql );

		if ( 0 !== $wpdb->num_rows ) {
			$this->id          = $answer->id;
			$this->superior_id = $answer->element_id;
			$this->label       = $answer->answer;
			$this->sort        = $answer->sort;
			$this->section     = $answer->section;
		}
	}

	protected function exists_in_db() {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT COUNT( id ) FROM {$wpdb->torro_element_answers} WHERE id = %d", $this->id );
		$var = $wpdb->get_var( $sql );

		if ( $var > 0 ) {
			return true;
		}

		return false;
	}

	protected function save_to_db() {
		global $wpdb;

		if ( ! empty( $this->id ) ) {
			$status = $wpdb->update( $wpdb->torro_element_answers, array(
				'element_id' => $this->superior_id,
				'answer'     => $this->label,
				'sort'       => $this->sort,
				'section'    => $this->section
			), array(
				'id' => $this->id
			) );
			if ( ! $status ) {
				return new Torro_Error( 'cannot_update_db', __( 'Could not update element answer in the database.', 'torro-forms' ), __METHOD__ );
			}
		} else {
			$status = $wpdb->insert( $wpdb->torro_element_answers, array(
				'element_id' => $this->superior_id,
				'answer'  	=> $this->label,
				'sort'    	=> $this->sort,
				'section' 	=> $this->section
			) );
			if ( ! $status ) {
				return new Torro_Error( 'cannot_insert_db', __( 'Could not insert element answer into the database.', 'torro-forms' ), __METHOD__ );
			}

			$this->id = $wpdb->insert_id;
		}

		return $this->id;
	}

	protected function delete_from_db() {
		global $wpdb;

		if ( empty( $this->id ) ) {
			return new Torro_Error( 'cannot_delete_empty', __( 'Cannot delete element answer without ID.', 'torro-forms' ), __METHOD__ );
		}

		return $wpdb->delete( $wpdb->torro_element_answers, array( 'id' => $this->id ) );
	}
}
