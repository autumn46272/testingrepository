<?php
/**
 * JavaScript Bridge for SCORM Communication
 * Communicates between iSpring player and our server
 * NCLEX-SCORM
 */
?>
<script>
/**
 * SCORM Communication Bridge
 * Handles communication between the iSpring SCORM player and the server
 */

class SCORMBridge {
    constructor() {
        this.apiUrl = window.scormData?.apiUrl || '/nclex-scorm/api/scorm_handler.php';
        this.packageId = window.scormData?.packageId;
        this.attemptId = window.scormData?.attemptId;
        this.userId = window.scormData?.userId;
        this.startTime = Date.now();
        this.cmiData = {};
    }

    /**
     * Initialize SCORM tracking
     */
    async initialize() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'initialize',
                    package_id: this.packageId
                })
            });

            const data = await response.json();
            if (data.success) {
                this.cmiData = data.cmi || {};
                console.log('SCORM initialized:', this.cmiData);
                return data;
            } else {
                throw new Error(data.error || 'Initialization failed');
            }
        } catch (error) {
            console.error('SCORM initialization error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Commit SCORM tracking data
     * Called periodically or at specific events
     */
    async commit(data = {}) {
        try {
            // Merge with existing data
            const commitData = { ...this.cmiData, ...data };
            this.cmiData = commitData;

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'commit',
                    package_id: this.packageId,
                    attempt_id: this.attemptId,
                    data: commitData
                })
            });

            const result = await response.json();
            if (!result.success) {
                console.error('Commit failed:', result.error);
            }
            return result;
        } catch (error) {
            console.error('SCORM commit error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Get a specific SCORM tracking value
     */
    async getValue(element) {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'get_value',
                    package_id: this.packageId,
                    element: element
                })
            });

            const data = await response.json();
            return data.value || '';
        } catch (error) {
            console.error('SCORM getValue error:', error);
            return '';
        }
    }

    /**
     * Submit test results when student completes the test
     */
    async submitTest(testResults = {}) {
        try {
            const duration = Math.floor((Date.now() - this.startTime) / 1000);

            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'submit_test',
                    package_id: this.packageId,
                    attempt_id: this.attemptId,
                    score: testResults.score || 0,
                    total_questions: testResults.total_questions || 0,
                    correct_answers: testResults.correct_answers || 0,
                    duration_seconds: duration,
                    test_results: testResults.results || [],
                    submitted_data: testResults
                })
            });

            const result = await response.json();
            if (result.success) {
                console.log('Test submitted successfully:', result);
                // Redirect to results page after submission
                setTimeout(() => {
                    window.location.href = '/nclex-scorm/student/package_report.php?id=' + this.packageId;
                }, 2000);
            } else {
                console.error('Test submission failed:', result.error);
            }
            return result;
        } catch (error) {
            console.error('SCORM submitTest error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Finish and complete the attempt
     */
    async finish() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'finish',
                    package_id: this.packageId,
                    attempt_id: this.attemptId
                })
            });

            const result = await response.json();
            return result;
        } catch (error) {
            console.error('SCORM finish error:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Suspend attempt (student may resume later)
     */
    async suspend() {
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'suspend',
                    package_id: this.packageId,
                    attempt_id: this.attemptId
                })
            });

            const result = await response.json();
            return result;
        } catch (error) {
            console.error('SCORM suspend error:', error);
            return { success: false, error: error.message };
        }
    }
}

// Initialize global SCORM bridge instance
const scormBridge = new SCORMBridge();

// Initialize on page load
document.addEventListener('DOMContentLoaded', async () => {
    await scormBridge.initialize();
});

// Handle page unload (suspend attempt)
window.addEventListener('beforeunload', async (e) => {
    await scormBridge.suspend();
});
</script>
