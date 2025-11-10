// admin/js/UserManagementHelpers/FileUploader.js

/**
 * FileUploader.js
 *
 * Manages the drag-and-drop file upload interface and
 * provides a method for parsing the selected file.
 */

export class FileUploader {
    constructor(dropzone, fileInput) {
        this.dropzone = dropzone;
        this.fileInput = fileInput;

        this.onFileReady = () => {}; // Callback to be set by UserManager

        this.setupEventListeners();
    }

    setupEventListeners() {
        if (!this.dropzone || !this.fileInput) {
            return;
        }

        // Handle visual styling on drag events
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                this.dropzone.style.borderColor = '#0073aa';
                this.dropzone.style.backgroundColor = '#eef6fc';
                this.dropzone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                this.dropzone.style.borderColor = '#ccc';
                this.dropzone.style.backgroundColor = '#fcfcfc';
                this.dropzone.classList.remove('drag-over');
            });
        });

        // ✅ Enable click-to-select file
        this.dropzone.addEventListener('click', () => {
            this.fileInput.click();
        });

        // File selected via dialog
        this.fileInput.addEventListener('change', e => {
            if (e.target.files.length > 0) {
                this.processFile(e.target.files[0]);
            }
        });

        // File dropped into dropzone
        this.dropzone.addEventListener('drop', e => {
            e.preventDefault();
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.processFile(files[0]);
            }
        });
    }


    async processFile(file) {
        if (!file) {
            alert('No file selected.');
            return;
        }

        if (!file.name.endsWith('.csv') && !file.name.endsWith('.xlsx')) {
            alert('Please upload a CSV or XLSX file.');
            this.reset();
            return;
        }

        try {
            const rows = await this.readFile(file);
            if (rows.length === 0) {
                alert('The selected file is empty.');
                this.reset();
                return;
            }

            const headers = rows[0];
            const dataRows = rows.slice(1);

            // Call the callback provided by UserManager
            this.onFileReady(dataRows, headers);

        } catch (error) {
            alert(`Error processing file: ${error.message}`);
            this.reset();
        }
    }

    readFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            const isExcel = file.name.endsWith('.xlsx');

            reader.onload = function (e) {
                let rows = [];
                if (isExcel) {
                    if (typeof XLSX === 'undefined') {
                        return reject(new Error('SheetJS (XLSX) library not loaded.'));
                    }
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const sheet = workbook.Sheets[workbook.SheetNames[0]];
                    rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                } else {
                    const text = e.target.result;
                    rows = text.trim().split('\n').map(line => line.split(',').map(s => s.trim()));
                }
                resolve(rows);
            };

            reader.onerror = () => reject(new Error('Failed to read file.'));

            if (isExcel) {
                reader.readAsArrayBuffer(file);
            } else {
                reader.readAsText(file);
            }
        });
    }

    reset() {
        if (this.fileInput) {
            this.fileInput.value = ''; // Reset the file input
        }
        if (this.dropzone) {
            this.dropzone.style.borderColor = '#ccc';
            this.dropzone.style.backgroundColor = '#fcfcfc';
            this.dropzone.classList.remove('drag-over');
        }
    }
}