<?php
/**
 * Core: Torro_Field_Mapper class
 *
 * @package TorroForms
 * @subpackage CoreModels
 * @version 1.0.0-beta.6
 * @since 1.0.0-beta.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Torro Forms Main Extension Class
 *
 * This class is the base for every Torro Forms Extension.
 *
 * @since 1.0.0-beta.1
 */
abstract class Torro_Field_Mapper extends Torro_Base {

	/**
	 * The fields this mapper requires.
	 *
	 * @since 1.1.0
	 * @var array
	 */
	protected $fields = array();

	/**
	 * Initializing.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		parent::__construct();
		$this->validate_fields();
		add_filter( 'torro_response_status', array( $this, 'check_response' ), 10, 5 );
	}

	public function get_fields() {
		return $this->fields;
	}

	public function get_mappings( $form_id ) {
		$form = torro()->forms()->get( $form_id );
		if ( is_wp_error( $form ) ) {
			return $form;
		}

		$mappings = $this->fields;
		foreach ( $form->elements as $element ) {
			$settings = $element->settings;
			if ( ! isset( $settings[ $this->name . '_mapping' ] ) ) {
				continue;
			}

			if ( ! isset( $mappings[ $settings[ $this->name . '_mapping' ]->value ] ) ) {
				continue;
			}

			$mappings[ $settings[ $this->name . '_mapping' ]->value ]['element'] = $element;
		}

		$validated_mappings = array();
		foreach ( $mappings as $slug => $field ) {
			if ( ! isset( $field['element'] ) ) {
				if ( $field['required'] ) {
					return new Torro_Error( 'missing_mapping', sprintf( __( 'The required field %s is missing a mapping.', 'torro-forms' ), $field['title'] ) );
				}
				continue;
			}
			$validated_mappings[ $slug ] = $field;
		}

		return $validated_mappings;
	}

	public function get_mapped_values( $result_id ) {
		$result = torro()->results()->get( $result_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$mappings = $this->get_mappings( $result->form_id );
		if ( is_wp_error( $mappings ) ) {
			return $mappings;
		}

		$lookup = array();
		foreach ( $mappings as $slug => $mapping ) {
			$lookup[ $mapping['element']->id ] = $slug;
		}

		foreach ( $result->values as $value ) {
			if ( ! isset( $lookup[ $value->element_id ] ) ) {
				continue;
			}
			$slug = $lookup[ $value->element_id ];
			$mappings[ $slug ]['value'] = $this->validate_from_choices( $value->value, $mappings[ $slug ]['choices'], $mappings[ $slug ]['type'] );
		}

		foreach ( $mappings as $slug => $mapping ) {
			if ( ! isset( $mapping['value'] ) ) {
				if ( $mapping['required'] ) {
					return new Torro_Error( 'missing_value', sprintf( __( 'The required field %s is missing a value.', 'torro-forms' ), $field['title'] ) );
				}
				$mappings[ $slug ]['value'] = $mapping['default'];
			}
		}

		return $mappings;
	}

	public function check_response( $status, $form_id, $container_id, $is_submit, $cache ) {
		if ( ! $is_submit ) {
			return $status;
		}

		if ( ! get_post_meta( $form_id, $this->name . '_mapping_active', true ) ) {
			return $status;
		}

		$result = $this->validate_response( $cache->get_response(), $form_id );
		if ( is_wp_error( $result ) ) {
			$cache->add_global_error( $result );
			return false;
		}

		return $status;
	}

	public function validate_response( $response, $form_id ) {
		//TODO
	}

	protected function validate_fields() {
		$defaults = array(
			'type'				=> 'string',
			'title'				=> '',
			'description'		=> '',
			'required'			=> false,
			'choices'			=> false,
		);

		foreach ( $this->fields as $slug => $field ) {
			$this->fields[ $slug ] = wp_parse_args( $field, $defaults );
			if ( isset( $this->fields[ $slug ]['element'] ) ) {
				unset( $this->fields[ $slug ]['element'] );
			}
			if ( isset( $this->fields[ $slug ]['value'] ) ) {
				unset( $this->fields[ $slug ]['value'] );
			}
			if ( ! isset( $this->fields[ $slug ]['default'] ) ) {
				$this->fields[ $slug ]['default'] = $this->get_default_from_choices( $this->fields[ $slug ]['choices'], $this->fields[ $slug ]['type'] );
			}
		}
	}

	protected function validate_from_choices( $value, $choices, $type ) {
		switch ( $type ) {
			case 'float':
				$value = floatval( $value );
				break;
			case 'int':
				$value = intval( $value );
				break;
			case 'bool':
				$value = ( ! $value || in_array( $value, array( 'no', 'false', 'NO', 'FALSE' ) ) ) ? false : true;
				break;
			case 'string':
			default:
				$value = strval( $value );
		}

		if ( is_array( $choices ) && 0 < count( $choices ) ) {
			if ( isset( $choices[0] ) ) {
				if ( ! in_array( $value, $choices ) ) {
					return new Torro_Error( 'invalid_value', sprintf( __( 'The value %s does not match any of the given choices.', 'torro-forms' ), $value ) );
				}
			} else {
				if ( ! isset( $choices[ $value ] ) ) {
					$key = array_search( $value, $choices );
					if ( false === $key ) {
						return new Torro_Error( 'invalid_value', sprintf( __( 'The value %s does not match any of the given choices.', 'torro-forms' ), $value ) );
					}
					$value = $key;
				}
			}
		} elseif ( is_string( $choices ) && false !== strpos( $choices, '-' ) ) {
			list( $min, $max ) = array_map( 'trim', explode( '-', $choices ) );
			if ( 'float' === $type ) {
				$min = floatval( $min );
				$max = floatval( $max );
			} else {
				$min = intval( $min );
				$max = intval( $max );
			}

			if ( $value > $max ) {
				return new Torro_Error( 'invalid_value', sprintf( __( 'The value %s is too high.', 'torro-forms' ), $value ) );
			} elseif ( $value < $min ) {
				return new Torro_Error( 'invalid_value', sprintf( __( 'The value %s is too low.', 'torro-forms' ), $value ) );
			}
		}

		return $value;
	}

	protected function get_default_from_choices( $choices, $type ) {
		if ( is_array( $choices ) && 0 < count( $choices ) ) {
			if ( isset( $choices[0] ) ) {
				return $choices[0];
			}
			return array_keys( $choices)[0];
		}

		if ( is_string( $choices ) && false !== strpos( $choices, '-' ) ) {
			list( $min, $max ) = array_map( 'trim', explode( '-', $choices ) );
			if ( 'float' === $type ) {
				return floatval( $min );
			}
			return intval( $min );
		}

		switch ( $type ) {
			case 'float':
				return 0.0;
			case 'int':
				return 0;
			case 'bool':
				return false;
			case 'string':
			default:
				return '';
		}
	}
}