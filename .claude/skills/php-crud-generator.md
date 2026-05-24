# Skill: PHP CRUD Generator
Generates full MVC CRUD for a given database table respecting project conventions.

**Input**: Table name, columns (with types), primary key.
**Output**:
- Model with methods: `find($id)`, `all($filters, $sort, $page)`, `create($data)`, `update($id, $data)`, `delete($id)`.
- Controller with actions: `index()`, `create()`, `store()`, `show()`, `edit()`, `update()`, `destroy()`.
- View templates: index (table with actions), form (with 4+ field types), confirmation for delete.
- Validation rules: required, length, format (email, number, etc.).
- Preserve old input & flash messages on validation errors.
- File upload handling if specified column type is `file`.