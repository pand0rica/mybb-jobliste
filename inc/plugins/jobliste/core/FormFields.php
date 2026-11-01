<?php
/**
 * Jobliste - gemeinsame Formular-Bausteine fuer die Berufsauswahl
 *
 * Werden vom eigenen Formular (jobliste.php) UND von den Feldern im Personenformular des
 * Relatives-Plugins (Hook relatives_person_form_fields, laeuft in relatives.php) genutzt.
 * Deshalb liegen sie hier und nicht in jobliste.php. $prefix steht vor allen Input-Namen
 * (category_id, custom_label, level[...]): '' im eigenen Formular, 'jobliste_' im
 * fremden Formular, damit es keine Namenskollisionen gibt.
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

/**
 * <option>-Liste des Kategoriebaums fuer das Kombo-Feld. Aktive Kategorien plus
 * (beim Bearbeiten) die aktuell gewaehlte, auch wenn sie inzwischen inaktiv ist.
 */
function jobliste_category_options(int $selected_id): string
{
    $options = '';
    foreach (Jobliste_Categories::get_flat_tree(false) as $cat) {
        if ($cat['is_active'] !== 1 && $cat['id'] !== $selected_id) {
            continue;
        }
        // Inaktive Vorfahren blenden den ganzen Zweig aus.
        $hidden = false;
        foreach (Jobliste_Categories::get_path($cat['id']) as $ancestor) {
            if ($ancestor['is_active'] !== 1 && $ancestor['id'] !== $selected_id) {
                $hidden = true;
                break;
            }
        }
        if ($hidden && $cat['id'] !== $selected_id) {
            continue;
        }
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $cat['depth']);
        $options .= '<option value="' . $cat['id'] . '"'
            . ' data-path="' . htmlspecialchars_uni($cat['path']) . '"'
            . ' data-custom="' . ($cat['allow_custom_text'] ? '1' : '0') . '"'
            . ' data-type="' . htmlspecialchars_uni($cat['type']) . '"'
            . ' data-depth="' . $cat['depth'] . '"'
            . ($cat['id'] === $selected_id ? ' selected="selected"' : '')
            . '>' . $indent . htmlspecialchars_uni($cat['name']) . '</option>';
    }
    return $options;
}

/**
 * Auswahlfelder fuer die Ebenen aller Kategorien vom Typ "levels" (je Kategorie eine
 * Gruppe, name="<prefix>level[<category_id>][<ebene>]"). Ohne JS sind alle Gruppen
 * sichtbar, der Server wertet nur die Gruppe der gewaehlten Kategorie aus; jobliste.js
 * blendet die uebrigen aus.
 *
 * @param string[] $current_values bisherige Werte (Bearbeiten), bleiben waehlbar
 * @param string   $prefix         Praefix der Input-Namen/-IDs ('' = eigenes Formular)
 */
function jobliste_level_fields(int $current_category_id, array $current_values, string $prefix = ''): string
{
    global $lang;

    $groups = '';
    foreach (Jobliste_Categories::get_flat_tree(false) as $cat) {
        if ($cat['type'] !== 'levels' || ($cat['is_active'] !== 1 && $cat['id'] !== $current_category_id)) {
            continue;
        }
        $levels = Jobliste_Levels::get_levels($cat['id']);
        if (!$levels) {
            continue;
        }
        $fields = '';
        foreach ($levels as $index => $level) {
            $selected = $cat['id'] === $current_category_id ? ($current_values[$index] ?? '') : '';
            $options = Jobliste_Levels::get_options($level);
            if ($selected !== '' && !in_array($selected, $options, true)) {
                $options[] = $selected; // alter Wert (z.B. aus der Migration) bleibt waehlbar
            }
            $select_id = ($prefix === '' ? 'jobliste_level_' : $prefix . 'field_level_') . $cat['id'] . '_' . $index;
            $html = '<option value="">' . htmlspecialchars_uni($lang->jobliste_level_choose) . '</option>';
            foreach ($options as $option) {
                $html .= '<option value="' . htmlspecialchars_uni($option) . '"' . ($option === $selected ? ' selected="selected"' : '') . '>'
                    . htmlspecialchars_uni($option) . '</option>';
            }
            $fields .= '<label class="jobliste_level" for="' . $select_id . '"><span>' . htmlspecialchars_uni($level['label']) . '</span>'
                . '<select name="' . $prefix . 'level[' . $cat['id'] . '][' . $index . ']" id="' . $select_id . '">' . $html . '</select></label>';
        }
        $groups .= '<div class="jobliste_level_group" data-category="' . $cat['id'] . '">'
            . '<div class="jobliste_level_group_title smalltext">' . htmlspecialchars_uni($cat['path']) . '</div>'
            . $fields . '</div>';
    }
    return $groups;
}

/**
 * Prueft Kategorie, Ebenenwerte und Freitext aus dem Formular.
 *
 * @param int|null $current_category_id beim Bearbeiten: bisherige Kategorie (darf
 *                                      inaktiv sein, solange sie nicht gewechselt wird)
 * @param string[] $current_values      beim Bearbeiten: bisherige Ebenenwerte
 * @param string   $prefix              Praefix der Input-Namen ('' = eigenes Formular)
 * @return array [Fehlertext|'' , category_id, custom_label|null, level_values|null]
 */
function jobliste_validate_job_input(?int $current_category_id = null, array $current_values = [], string $prefix = ''): array
{
    global $mybb, $lang;

    $lang->load('jobliste');
    $category_id = $mybb->get_input($prefix . 'category_id', MyBB::INPUT_INT);
    $custom_label = Jobliste_Database::clean_label($mybb->get_input($prefix . 'custom_label'));

    $category = $category_id > 0 ? Jobliste_Categories::get($category_id) : null;
    if ($category === null) {
        return [$lang->jobliste_error_category_required, 0, null, null];
    }
    if ($category['is_active'] !== 1 && $category_id !== $current_category_id) {
        return [$lang->jobliste_error_category_inactive, 0, null, null];
    }
    if ($custom_label !== null && $category['allow_custom_text'] !== 1) {
        return [$lang->jobliste_error_custom_not_allowed, 0, null, null];
    }

    $level_values = null;
    if ($category['type'] === 'levels') {
        $input = $mybb->get_input($prefix . 'level', MyBB::INPUT_ARRAY);
        [$ok, $level_values] = Jobliste_Levels::validate_values(
            $category_id,
            $input[$category_id] ?? [],
            $category_id === $current_category_id ? $current_values : null
        );
        if (!$ok) {
            return [$lang->jobliste_error_levels_required, 0, null, null];
        }
    }
    return ['', $category_id, $custom_label, $level_values];
}
