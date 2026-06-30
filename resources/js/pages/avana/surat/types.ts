/**
 * Shared types for the AvanaHR letter template (Template Surat) module pages.
 * These mirror the `LetterTemplateController` payloads (`index`, `create`,
 * `edit`, `print`).
 */

export type { FlashProps } from '../employees/types';

/** A `{ value, label }` option used by enum selects. */
export interface SelectOption {
    value: string;
    label: string;
}

/** A placeholder token hint shown beside the template body editor. */
export interface PlaceholderToken {
    token: string;
    label: string;
}

/** A letter template row as serialized by `LetterTemplateController@index`. */
export interface TemplateRow {
    id: number;
    name: string;
    type: string;
    type_label: string;
    is_active: boolean;
    updated_at: string | null;
}

/** A generated letter row as serialized by `LetterTemplateController@index`. */
export interface GeneratedLetterRow {
    id: number;
    title: string;
    employee_name: string | null;
    letter_number: string | null;
    generated_at: string | null;
}

/** A selectable employee `{ id, name }`. */
export interface EmployeeOption {
    id: number;
    name: string;
}

/** A selectable template `{ value, label }` for the generate panel. */
export interface TemplateOption {
    value: number;
    label: string;
}

/** Props for the letter templates index page (`index.tsx`). */
export interface SuratIndexProps {
    templates: TemplateRow[];
    generatedLetters: GeneratedLetterRow[];
    employees: EmployeeOption[];
    templateOptions: TemplateOption[];
    types: SelectOption[];
}

/** A single template payload (`LetterTemplateController@edit`). */
export interface TemplateDetail {
    id: number;
    name: string;
    type: string;
    body: string;
    is_active: boolean;
}

/** Props for the create page (`create.tsx`). */
export interface SuratCreateProps {
    types: SelectOption[];
    placeholders: PlaceholderToken[];
}

/** Props for the edit page (`edit.tsx`). */
export interface SuratEditProps {
    template: TemplateDetail;
    types: SelectOption[];
    placeholders: PlaceholderToken[];
}

/** A printable generated letter payload (`LetterTemplateController@print`). */
export interface PrintLetter {
    id: number;
    title: string;
    body: string;
    letter_number: string | null;
    generated_at: string | null;
    generated_at_label: string | null;
}

/** Props for the printable letter page (`print.tsx`). */
export interface SuratPrintProps {
    letter: PrintLetter;
    company: {
        name: string;
    };
}

/** Flat form payload backing both the create and edit template forms. */
export interface TemplateFormData {
    name: string;
    type: string;
    body: string;
    is_active: boolean;
}

/** Empty defaults for the create template form. */
export const emptyTemplateForm: TemplateFormData = {
    name: '',
    type: 'custom',
    body: '',
    is_active: true,
};

/** Flat form payload backing the generate-letter modal on the index page. */
export interface GenerateFormData {
    letter_template_id: string;
    employee_id: string;
    letter_number: string;
    generated_at: string;
}

/** Empty defaults for the generate-letter form. */
export const emptyGenerateForm: GenerateFormData = {
    letter_template_id: '',
    employee_id: '',
    letter_number: '',
    generated_at: '',
};

/** Resolve the Indonesian label for a template type enum value. */
export function typeLabel(type: string, types: SelectOption[]): string {
    return types.find((option) => option.value === type)?.label ?? type;
}
