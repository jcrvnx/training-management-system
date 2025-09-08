<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pdf_generator {

    protected $CI;
    protected $html;
    protected $options;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->helper('file');
        
        $this->options = [
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_left' => 20,
            'margin_right' => 20,
            'encoding' => 'UTF-8'
        ];
    }

    /**
     * Set HTML content for PDF generation
     */
    public function set_html($html) {
        $this->html = $html;
        return $this;
    }

    /**
     * Set PDF options
     */
    public function set_options($options) {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    /**
     * Generate PDF and return file path
     */
    public function generate($filename = null, $save_path = null) {
        if (!$this->html) {
            throw new Exception('HTML content not set');
        }

        // Generate filename if not provided
        if (!$filename) {
            $filename = 'document_' . date('YmdHis') . '.pdf';
        }

        // Set save path
        if (!$save_path) {
            $save_path = FCPATH . 'uploads/certificates/';
        }

        // Create directory if it doesn't exist
        if (!is_dir($save_path)) {
            mkdir($save_path, 0755, true);
        }

        $file_path = $save_path . $filename;

        // For demonstration, we'll save the HTML as a file
        // In a real implementation, you would use a library like TCPDF, DOMPDF, or wkhtmltopdf
        $html_content = $this->_prepare_html_for_pdf();
        
        // Simple HTML to "PDF" conversion (saves as HTML file)
        // In production, integrate with actual PDF library
        if (write_file($file_path, $html_content)) {
            return $file_path;
        }

        throw new Exception('Failed to generate PDF');
    }

    /**
     * Generate and download PDF
     */
    public function download($filename = null) {
        $file_path = $this->generate($filename);
        
        if (file_exists($file_path)) {
            $this->CI->load->helper('download');
            $data = file_get_contents($file_path);
            
            // Set proper headers for PDF download
            force_download($filename ?: basename($file_path), $data);
            
            // Clean up temporary file
            unlink($file_path);
        }
    }

    /**
     * Generate certificate PDF
     */
    public function generate_certificate($certificate_data, $template = null) {
        if (!$template) {
            $template = $this->_get_default_certificate_template();
        }

        // Replace template placeholders
        $html = $this->_replace_template_variables($template, $certificate_data);
        
        $this->set_html($html);
        $this->set_options([
            'page_size' => 'A4',
            'orientation' => 'landscape'
        ]);

        $filename = 'certificate_' . $certificate_data['certificate_number'] . '.pdf';
        return $this->generate($filename);
    }

    /**
     * Generate training report PDF
     */
    public function generate_training_report($training_data, $enrollments = []) {
        $html = $this->_get_training_report_template($training_data, $enrollments);
        
        $this->set_html($html);
        $this->set_options([
            'page_size' => 'A4',
            'orientation' => 'portrait'
        ]);

        $filename = 'training_report_' . $training_data['id'] . '_' . date('YmdHis') . '.pdf';
        return $this->generate($filename);
    }

    /**
     * Prepare HTML for PDF conversion
     */
    private function _prepare_html_for_pdf() {
        $html = $this->html;
        
        // Add CSS for better PDF formatting
        $pdf_css = "
        <style>
            body { 
                font-family: 'DejaVu Sans', Arial, sans-serif; 
                font-size: 12px; 
                line-height: 1.4;
                margin: {$this->options['margin_top']}mm {$this->options['margin_right']}mm {$this->options['margin_bottom']}mm {$this->options['margin_left']}mm;
            }
            .page-break { page-break-before: always; }
            .no-break { page-break-inside: avoid; }
            @media print {
                .no-print { display: none !important; }
            }
        </style>";
        
        // Insert CSS into HTML head or create head if it doesn't exist
        if (strpos($html, '<head>') !== false) {
            $html = str_replace('<head>', '<head>' . $pdf_css, $html);
        } else {
            $html = '<head>' . $pdf_css . '</head>' . $html;
        }
        
        return $html;
    }

    /**
     * Replace template variables with actual data
     */
    private function _replace_template_variables($template, $data) {
        $replacements = [
            '{{USER_NAME}}' => $data['user_name'] ?? '',
            '{{TRAINING_TITLE}}' => $data['training_title'] ?? '',
            '{{CERTIFICATE_NUMBER}}' => $data['certificate_number'] ?? '',
            '{{COMPLETION_DATE}}' => isset($data['completion_date']) ? date('F j, Y', strtotime($data['completion_date'])) : date('F j, Y'),
            '{{ISSUE_DATE}}' => isset($data['issued_date']) ? date('F j, Y', strtotime($data['issued_date'])) : date('F j, Y'),
            '{{INSTRUCTOR_NAME}}' => $data['instructor_name'] ?? 'Training Instructor',
            '{{TRAINING_DESCRIPTION}}' => $data['training_description'] ?? '',
            '{{DURATION_HOURS}}' => $data['duration_hours'] ?? '',
            '{{VERIFICATION_CODE}}' => $data['verification_code'] ?? ''
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get default certificate template
     */
    private function _get_default_certificate_template() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Certificate of Completion</title>
            <style>
                body { 
                    font-family: "Times New Roman", serif; 
                    text-align: center; 
                    padding: 40px; 
                    background: linear-gradient(45deg, #f0f8ff, #e6f3ff);
                }
                .certificate { 
                    border: 12px solid #4CAF50; 
                    padding: 50px; 
                    background: white; 
                    box-shadow: 0 0 20px rgba(0,0,0,0.1);
                    position: relative;
                }
                .header { 
                    font-size: 42px; 
                    color: #4CAF50; 
                    margin-bottom: 20px; 
                    font-weight: bold; 
                }
                .subtitle { font-size: 20px; margin: 15px 0; color: #333; }
                .name { 
                    font-size: 36px; 
                    color: #2c5aa0; 
                    margin: 25px 0; 
                    font-weight: bold; 
                    text-decoration: underline;
                }
                .course { 
                    font-size: 28px; 
                    color: #4CAF50; 
                    margin: 20px 0; 
                    font-style: italic; 
                }
                .date { font-size: 16px; margin: 25px 0; color: #666; }
                .signature { 
                    margin-top: 50px; 
                    display: flex; 
                    justify-content: space-between; 
                }
                .signature div { text-align: center; }
                .signature-line { 
                    border-top: 2px solid #333; 
                    width: 180px; 
                    margin: 15px auto 5px; 
                }
                .cert-number { 
                    position: absolute; 
                    bottom: 15px; 
                    right: 15px; 
                    font-size: 10px; 
                    color: #999; 
                }
            </style>
        </head>
        <body>
            <div class="certificate">
                <div class="header">CERTIFICATE OF COMPLETION</div>
                <div class="subtitle">This is to certify that</div>
                <div class="name">{{USER_NAME}}</div>
                <div class="subtitle">has successfully completed the training program</div>
                <div class="course">{{TRAINING_TITLE}}</div>
                <div class="date">Completed on {{COMPLETION_DATE}}</div>
                <div class="signature">
                    <div>
                        <div class="signature-line"></div>
                        <div>{{INSTRUCTOR_NAME}}</div>
                        <div>Training Instructor</div>
                    </div>
                    <div>
                        <div class="signature-line"></div>
                        <div>Training Administrator</div>
                        <div>Training Management System</div>
                    </div>
                </div>
                <div class="cert-number">Certificate #: {{CERTIFICATE_NUMBER}}</div>
            </div>
        </body>
        </html>';
    }

    /**
     * Get training report template
     */
    private function _get_training_report_template($training, $enrollments) {
        $enrollment_rows = '';
        foreach ($enrollments as $enrollment) {
            $status_class = $enrollment['status'] === 'completed' ? 'success' : 
                           ($enrollment['status'] === 'in_progress' ? 'warning' : 'default');
            
            $enrollment_rows .= "
            <tr>
                <td>{$enrollment['user_name']}</td>
                <td>{$enrollment['user_email']}</td>
                <td><span class='status {$status_class}'>" . ucfirst($enrollment['status']) . "</span></td>
                <td>{$enrollment['progress_percentage']}%</td>
                <td>" . date('M j, Y', strtotime($enrollment['enrollment_date'])) . "</td>
                <td>" . ($enrollment['completion_date'] ? date('M j, Y', strtotime($enrollment['completion_date'])) : '-') . "</td>
            </tr>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Training Report - {$training['title']}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .training-info { background: #f8f9fa; padding: 20px; margin-bottom: 30px; }
                .training-info h3 { margin-top: 0; color: #2c5aa0; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #4CAF50; color: white; }
                .status { padding: 4px 8px; border-radius: 4px; color: white; font-size: 12px; }
                .status.success { background-color: #28a745; }
                .status.warning { background-color: #ffc107; color: #333; }
                .status.default { background-color: #6c757d; }
                .summary { margin-top: 30px; display: flex; justify-content: space-around; }
                .summary div { text-align: center; }
                .summary h4 { color: #2c5aa0; margin-bottom: 5px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Training Report</h1>
                <p>Generated on " . date('F j, Y g:i A') . "</p>
            </div>
            
            <div class='training-info'>
                <h3>{$training['title']}</h3>
                <p><strong>Description:</strong> {$training['description']}</p>
                <p><strong>Duration:</strong> {$training['duration_hours']} hours</p>
                <p><strong>Start Date:</strong> " . date('F j, Y', strtotime($training['start_date'])) . "</p>
                <p><strong>End Date:</strong> " . date('F j, Y', strtotime($training['end_date'])) . "</p>
                <p><strong>Status:</strong> " . ucfirst($training['status']) . "</p>
            </div>
            
            <h3>Enrollment Summary</h3>
            <div class='summary'>
                <div>
                    <h4>" . count($enrollments) . "</h4>
                    <p>Total Enrolled</p>
                </div>
                <div>
                    <h4>" . count(array_filter($enrollments, function($e) { return $e['status'] === 'completed'; })) . "</h4>
                    <p>Completed</p>
                </div>
                <div>
                    <h4>" . count(array_filter($enrollments, function($e) { return $e['status'] === 'in_progress'; })) . "</h4>
                    <p>In Progress</p>
                </div>
            </div>
            
            <h3>Detailed Enrollments</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Enrolled Date</th>
                        <th>Completion Date</th>
                    </tr>
                </thead>
                <tbody>
                    {$enrollment_rows}
                </tbody>
            </table>
        </body>
        </html>";
    }
}
