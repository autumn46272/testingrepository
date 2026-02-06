/**
 * SCORM API Wrapper for iSpring Content
 * Intercepts SCORM communication and sends data to our server
 * NCLEX-SCORM
 */

// SCORM 1.2 API Wrapper
function createScormAPI() {
    const cmiData = {};
    let testStartTime = Date.now();
    let isInitialized = false;

    // State for managing NaN interaction indices
    const interactionIdToIndex = {};
    let nextInteractionIndex = 0;

    // Helper to find next available index
    function getNextIndex() {
        while (cmiData[`cmi.interactions.${nextInteractionIndex}.id`]) {
            nextInteractionIndex++;
        }
        return nextInteractionIndex;
    }

    const API = {
        LMSInitialize: function (param) {
            console.log('[SCORM] LMSInitialize called');
            isInitialized = true;
            testStartTime = Date.now();

            // Load existing CMI data from server
            fetch(window.scormData.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'initialize',
                    package_id: window.scormData.packageId,
                    attempt_id: window.scormData.attemptId
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.cmi) {
                        Object.assign(cmiData, data.cmi);
                        // Update nextInteractionIndex based on loaded data
                        nextInteractionIndex = 0;
                        Object.keys(cmiData).forEach(key => {
                            const match = key.match(/cmi\.interactions\.(\d+)\.id/);
                            if (match) {
                                const idx = parseInt(match[1]);
                                if (idx >= nextInteractionIndex) nextInteractionIndex = idx + 1;
                            }
                        });
                    }
                    console.log('[SCORM] Initialized with data:', cmiData);
                })
                .catch(err => console.error('[SCORM] Initialize error:', err));

            return 'true';
        },

        LMSFinish: function (param) {
            console.log('[SCORM] LMSFinish called');

            // Calculate test results
            const results = API.extractTestResults();
            console.log('[SCORM] Extracted results:', results);

            // Send results to server
            fetch(window.scormData.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'submit_test',
                    package_id: window.scormData.packageId,
                    attempt_id: window.scormData.attemptId,
                    score: results.score,
                    total_questions: results.total_questions,
                    correct_answers: results.correct_answers,
                    duration_seconds: results.duration_seconds,
                    test_results: results.questions,
                    submitted_data: cmiData
                })
            })
                .then(res => res.json())
                .then(data => {
                    console.log('[SCORM] Submit response:', data);
                    if (data.success) {
                        // Redirect to results page
                        setTimeout(() => {
                            window.top.location.href = window.scormData.apiUrl.replace('/api/scorm_handler.php', '') +
                                '/student/attempt_details.php?id=' + window.scormData.attemptId;
                        }, 1000);
                    }
                })
                .catch(err => console.error('[SCORM] Submit error:', err));

            isInitialized = false;
            return 'true';
        },

        LMSGetValue: function (element) {
            // Check if looking for NaN interaction
            if (element.indexOf('.interactions.NaN.') !== -1) {
                // Try to guess which index, or return empty
                // This is tricky for GetValue if we don't know context.
                // Retaining basic lookup for now, might need improvement later.
            }

            const value = cmiData[element] || '';
            console.log('[SCORM] LMSGetValue:', element, '=', value);
            return value;
        },

        LMSSetValue: function (element, value) {
            console.log('[SCORM] LMSSetValue (Raw):', element, '=', value);

            // FIX: Handle NaN indices from buggy SCORM content
            if (element.indexOf('cmi.interactions.NaN') !== -1) {
                // Determine property name (id, type, result, etc.)
                const match = element.match(/cmi\.interactions\.NaN\.(.*)/);
                if (match) {
                    const prop = match[1];
                    let useIndex = nextInteractionIndex; // Default to next new index

                    if (prop === 'id') {
                        // START OF NEW INTERACTION (OR EXISTING ONE)
                        // value is the interaction ID (e.g. Q_1234)

                        // Check if we already have an index for this ID
                        // First check our runtime map
                        if (interactionIdToIndex[value] !== undefined) {
                            useIndex = interactionIdToIndex[value];
                        } else {
                            // Check existing cmiData to see if this ID exists from a previous session
                            let existingIndex = -1;
                            for (let k in cmiData) {
                                if (k.match(/cmi\.interactions\.(\d+)\.id/) && cmiData[k] === value) {
                                    existingIndex = parseInt(k.match(/cmi\.interactions\.(\d+)\.id/)[1]);
                                    break;
                                }
                            }

                            if (existingIndex !== -1) {
                                useIndex = existingIndex;
                            } else {
                                // Truly new interaction
                                useIndex = getNextIndex();
                                // Increment for next time only if we consumed a new slot
                                // Actually getNextIndex finds the first empty slot.
                            }
                            // Store mapping
                            interactionIdToIndex[value] = useIndex;
                        }

                        // Set current context for subsequent calls (type, result)
                        // We store the mapping so subsequent calls can look it up?
                        // Problem: subsequent calls don't have the ID. They just have 'NaN'.

                        // STRATEGY: We assume the player sets .id FIRST for an interaction.
                        // We assume "current active NaN interaction" is the one we just touched.
                        window.currentNaNIndex = useIndex;

                    } else {
                        // Setting a property (type, result, etc.) but index is NaN
                        // Use the last touched index determined by .id
                        if (window.currentNaNIndex !== undefined) {
                            useIndex = window.currentNaNIndex;
                        } else {
                            // Fallback: if we haven't seen an .id yet, this is messy.
                            // Assume new index? Or 0?
                            useIndex = getNextIndex();
                            window.currentNaNIndex = useIndex;
                        }
                    }

                    // Rewrite element with correct index
                    element = element.replace('NaN', useIndex);
                    console.log('[SCORM] LMSSetValue (Fixed):', element, '=', value);
                }
            }

            cmiData[element] = value;

            // Auto-commit certain values
            if (element.indexOf('cmi.core.score') !== -1 ||
                element.indexOf('cmi.core.lesson_status') !== -1 ||
                element.indexOf('cmi.interactions') !== -1) {
                setTimeout(() => API.LMSCommit(''), 100);
            }

            return 'true';
        },

        LMSCommit: function (param) {
            console.log('[SCORM] LMSCommit called, data:', cmiData);

            // Send data to server
            fetch(window.scormData.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'commit',
                    package_id: window.scormData.packageId,
                    attempt_id: window.scormData.attemptId,
                    data: cmiData
                })
            })
                .then(res => res.json())
                .then(data => console.log('[SCORM] Commit success:', data))
                .catch(err => console.error('[SCORM] Commit error:', err));

            return 'true';
        },

        LMSGetLastError: function () {
            return '0';
        },

        LMSGetErrorString: function (errorCode) {
            return 'No error';
        },

        LMSGetDiagnostic: function (errorCode) {
            return 'No diagnostic';
        },

        // Extract test results from CMI data
        extractTestResults: function () {
            const duration = Math.floor((Date.now() - testStartTime) / 1000);

            // Parse score
            let score = 0;
            const rawScore = cmiData['cmi.core.score.raw'];
            const scaledScore = cmiData['cmi.core.score.scaled'];

            if (rawScore !== undefined) {
                score = parseFloat(rawScore);
            } else if (scaledScore !== undefined) {
                score = parseFloat(scaledScore) * 100;
            }

            // Count interactions (questions)
            let total_questions = 0;
            let correct_answers = 0;
            const questions = [];

            // Loop through CMI interactions - robust search for any interaction keys
            // Some SCORM packages use non-squential indices or NaN
            const interactionIndices = new Set();
            Object.keys(cmiData).forEach(key => {
                const match = key.match(/cmi\.interactions\.([^.]+)\.id/);
                if (match) {
                    interactionIndices.add(match[1]);
                }
            });

            interactionIndices.forEach(i => {
                const interactionId = cmiData[`cmi.interactions.${i}.id`];
                if (!interactionId) return;

                total_questions++;

                const result = cmiData[`cmi.interactions.${i}.result`] || '';
                const isCorrect = (result === 'correct' || result === 'true' || result === '1');
                const weight = parseFloat(cmiData[`cmi.interactions.${i}.weighting`]) || 1;

                if (isCorrect) {
                    correct_answers++;
                }

                questions.push({
                    question_id: interactionId,
                    question_text: cmiData[`cmi.interactions.${i}.description`] || '',
                    user_answer: cmiData[`cmi.interactions.${i}.student_response`] || '',
                    correct_answer: cmiData[`cmi.interactions.${i}.correct_responses.0.pattern`] || '',
                    is_correct: isCorrect,
                    points_earned: isCorrect ? weight : 0,
                    points_possible: weight,
                    answer_time_seconds: parseInt(cmiData[`cmi.interactions.${i}.latency`]) || 0
                });
            });

            // If no interactions found, try to parse from lesson_status
            if (total_questions === 0) {
                const lessonStatus = cmiData['cmi.core.lesson_status'];
                if (lessonStatus === 'passed' || lessonStatus === 'completed') {
                    // Estimate based on score
                    total_questions = 10; // Default assumption
                    correct_answers = Math.round((score / 100) * total_questions);
                }
            }

            return {
                score: score,
                total_questions: total_questions,
                correct_answers: correct_answers,
                duration_seconds: duration,
                questions: questions
            };
        }
    };

    return API;
}

// Initialize SCORM API when page loads
(function () {
    console.log('[SCORM] Initializing API wrapper');

    // Create the API object
    const API = createScormAPI();

    // Make it available globally
    window.API = API;
    window.API_1484_11 = API; // SCORM 2004 compatibility

    console.log('[SCORM] API ready, window.API =', window.API);
})();
