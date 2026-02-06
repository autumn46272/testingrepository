<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Student</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .waiting-message {
            font-style: italic;
            color: var(--text-muted);
            margin-top: 20px;
        }
        .login-box {
            max-width: 400px;
            margin: 0 auto;
        }

        /* SECURITY STYLES */
        #watermark-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            z-index: 9999;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-around;
            align-content: space-around;
            overflow: hidden;
            opacity: 0.08;
            /* Faint but visible */
        }

        .watermark-text {
            transform: rotate(-30deg);
            font-size: 1.2rem;
            color: #000;
            font-weight: bold;
            white-space: nowrap;
            user-select: none;
            padding: 40px;
        }

        #security-blur {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #000;
            color: #fff;
            z-index: 10000;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-family: sans-serif;
            text-align: center;
        }
    </style>
</head>
<body>
    <div id="security-blur">
        <div style="font-size: 4rem;">🛡️</div>
        <h3>Content Hidden</h3>
        <p>Screen recording or switching windows is restricted.</p>
        <p style="font-size: 0.9rem; opacity: 0.7;">Click anywhere to resume.</p>
    </div>
    <div class="container">
        <!-- LOGIN SCREEN -->
        <div id="login-screen" class="card login-box">
            <h1>Join Live Quiz</h1>
            <p>Enter your details to join</p>
            
            <input type="text" id="student-name" class="option-btn" 
                   style="text-align:center; cursor:text; margin-bottom: 0.5rem;" 
                   placeholder="Student ID">
                   
            <input type="text" id="exam-code" class="option-btn" 
                   style="text-align:center; cursor:text; margin-bottom: 1rem; letter-spacing: 2px;" 
                   placeholder="8-Digit Exam Code" maxlength="8">
                   
            <button class="btn" onclick="joinQuiz()">Join Session</button>
            <p id="login-error" style="color:red; margin-top:10px;"></p>
        </div>

        <!-- QUIZ SCREEN -->
        <div id="quiz-screen" class="card" style="display: none;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                <h1>Student View</h1>
                <div class="status-badge" id="conn-status">Connected</div>
            </div>
            
            <div style="background:#f3f4f6; color:#374151; padding:8px; border-radius:6px; font-size:0.9rem; margin-bottom:20px;">
                <span id="student-display" style="font-weight:bold;"></span> | 
                Session: <span id="session-display"></span>
            </div>
            
            <div id="loading" class="loading">Waiting for quiz to start...</div>
            
            <div id="quiz-content" style="display: none;">
                <h2 id="q-counter">Question</h2>
                <!-- Question text hidden -->
                <p id="q-text" class="question-text" style="display:none;"></p>
                
                <div class="options-grid" id="options-container"></div>
                
                <p id="feedback" class="waiting-message"></p>
            </div>
        </div>
    </div>

    <script>
        let studentId = sessionStorage.getItem('quiz_student_id');
        let studentName = sessionStorage.getItem('quiz_student_name');
        let examCode = sessionStorage.getItem('quiz_exam_code');
        
        let currentQuestionId = -1;
        let selectedOption = null;

        // Init
        if (studentId && studentName && examCode) {
            showQuiz(sessionStorage.getItem('quiz_exam_title'));
        }

        function showQuiz(examTitle) {
            document.getElementById('login-screen').style.display = 'none';
            document.getElementById('quiz-screen').style.display = 'block';
            document.getElementById('student-display').innerText = studentName;
            document.getElementById('session-display').innerText = examTitle || examCode;
            
            // initSecurity(); // Active Security Features - moved to joinQuiz
            // startPolling(); // moved to joinQuiz
        }

        function leaveQuiz() {
            if(confirm('Are you sure you want to disconnect?')) {
                sessionStorage.clear();
                window.location.reload();
            }
        }

        async function joinQuiz() {
            const name = document.getElementById('student-name').value.trim();
            const code = document.getElementById('exam-code').value.trim();
            
            if(!name || !code) {
                document.getElementById('login-error').innerText = 'Please enter Name and Code';
                return;
            }

            try {
                const res = await fetch('api.php?action=join_quiz', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ name, code })
                });
                const data = await res.json();
                
                if (data.status === 'success') {
                    studentId = data.student_id;
                    studentName = name;
                    examCode = code;
                    examId = data.exam_id; // Capture ID
                    
                    // Session Storage handles page refreshes better for short sessions
                    // sessionStorage.setItem('quiz_student_id', studentId);
                    // sessionStorage.setItem('quiz_student_name', studentName);
                    // sessionStorage.setItem('quiz_exam_code', examCode);
                    // sessionStorage.setItem('quiz_exam_title', data.exam_title);
                    
                    initSecurity();

                    document.getElementById('login-screen').style.display = 'none';
                    document.getElementById('quiz-screen').style.display = 'block';
                    
                    showQuiz(data.exam_title); // Update display elements
                    startPolling();
                } else {
                    document.getElementById('login-error').innerText = data.message;
                }
            } catch (e) {
                console.error(e);
                document.getElementById('login-error').innerText = 'Connection failed';
            }
        }

        let pollInterval;
        function startPolling() {
            pollInterval = setInterval(chkState, 2000);
            chkState();
        }

        async function chkState() {
            try {
                // Pass Exam Code
                const response = await fetch(`api.php?action=get_state&code=${examCode}`);
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Update connection UI
                    document.getElementById('conn-status').innerText = 'Connected';
                    document.getElementById('conn-status').style.backgroundColor = '#dcfce7';
                    document.getElementById('conn-status').style.color = '#166534';

                    const q = data.question;
                    const index = data.state.currentQuestionIndex;

                    // Check for Status
                    if (data.exam_status === 'completed') {
                        alert("The presenter has ended the session.");
                        window.location.href = `results.php?student_id=${studentId}&exam_id=${examId}`; // Use ID
                        return;
                    }

                    // If we have a new question (by ID or simply by index change)
                    if (q && q.id !== currentQuestionId) {
                        currentQuestionId = q.id;
                        selectedOption = null; // Reset selection for new question
                        renderQuestion(q, index, data.totalQuestions);
                    } else if (!q && index >= data.totalQuestions) {
                        // End of Quiz (Completed all Qs)
                        window.location.href = `results.php?student_id=${studentId}&exam_id=${examId}`; // Use ID
                    }
                }
            } catch (e) {
                console.error("Polling error", e);
                document.getElementById('conn-status').innerText = 'Reconnecting...';
                document.getElementById('conn-status').style.backgroundColor = '#fee2e2';
                document.getElementById('conn-status').style.color = '#991b1b';
            }
        }

        function renderQuestion(question, index, total) {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('quiz-content').style.display = 'block';
            document.getElementById('conn-status').innerText = 'Live';
            document.getElementById('conn-status').style.backgroundColor = '#dcfce7';
            document.getElementById('conn-status').style.color = '#166534';

            document.getElementById('q-counter').innerText = `Question ${index + 1}`;
            
            const container = document.getElementById('options-container');
            container.innerHTML = '';
            document.getElementById('feedback').innerText = '';

            if (question.type === 'text') {
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'option-btn'; 
                input.placeholder = 'Type your answer...';
                input.style.cursor = 'text';
                input.style.textAlign = 'center';
                
                const submitBtn = document.createElement('button');
                submitBtn.className = 'btn';
                submitBtn.style.marginTop = '10px';
                submitBtn.style.width = '100%';
                submitBtn.innerText = 'Submit Answer';
                
                submitBtn.onclick = () => {
                    const val = input.value.trim();
                    if (!val) return;
                    submitAnswer(val);
                    input.disabled = true;
                    submitBtn.disabled = true;
                };

                container.appendChild(input);
                container.appendChild(submitBtn);

            } else {
                question.options.forEach((opt, i) => {
                    const btn = document.createElement('button');
                    btn.className = 'option-btn';
                    btn.innerText = opt;
                    // For choice, we send 1-based index to match logic? Or text?
                    // API logic now: ($answerText == $expectedAnswer).
                    // In api.php create_exam, we stored JSON answers directly if generated from questions.json.
                    // setup_db used JSON for setup. In api.php create_exam, we use $q['answer']. 
                    // Usually JSON answer is "2" (index) or "Paris" (text).
                    // If questions.json has "answer": 2 (integer), then we should send index (i+1).
                    // Let's assume indices for choices.
                    // Backend uses 0-based index from questions.json
                    btn.onclick = () => {
                        submitAnswer(i); // sending 0-based index
                        selectButtonVisuals(btn);
                    };
                    container.appendChild(btn);
                });
            }
        }

        async function submitAnswer(answerVal) {
            if (selectedOption !== null) return;
            selectedOption = answerVal;

            document.getElementById('feedback').innerText = "Submitting...";
            
            try {
                const res = await fetch('api.php?action=submit_answer', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        student_id: studentId,
                        code: examCode,
                        question_id: currentQuestionId,
                        answer: answerVal
                    })
                });
                
                const data = await res.json();
                if (data.status === 'success') {
                    document.getElementById('feedback').innerText = "Answer Submitted!";
                    document.getElementById('feedback').style.color = "var(--primary-color)";
                } else {
                    document.getElementById('feedback').innerText = "Error submitting.";
                }
            } catch (e) {
                document.getElementById('feedback').innerText = "Error submitting.";
            }
        }

        function selectButtonVisuals(btnElement) {
            const allBtns = document.querySelectorAll('.option-btn');
            allBtns.forEach(b => b.classList.remove('selected'));
            btnElement.classList.add('selected');
        }

        // --- SECURITY FEATURES ---
        function initSecurity() {
            // 1. Watermark
            const watermarkContainer = document.createElement('div');
            watermarkContainer.id = 'watermark-overlay';
            document.body.appendChild(watermarkContainer);

            const info = `${studentName} (${studentId}) • ${new Date().toLocaleDateString()}`;
            
            // Create multiple instances to fill screen
            for(let i=0; i<30; i++) {
                const el = document.createElement('div');
                el.className = 'watermark-text';
                el.innerText = info;
                watermarkContainer.appendChild(el);
            }

            // 2. Focus Loss (Anti-Alt-Tab)
            window.addEventListener('blur', () => {
                setTimeout(() => {
                    // Check if active element is still in our window (e.g. iframe or input)
                    if (document.activeElement && document.activeElement.tagName === 'IFRAME') return;
                    document.getElementById('security-blur').style.display = 'flex';
                    document.title = '⚠️ Content Hidden';
                }, 100);
            });

            window.addEventListener('focus', () => {
                document.getElementById('security-blur').style.display = 'none';
                document.title = 'Quiz Student';
            });

            // 3. Disable Right Click
            document.addEventListener('contextmenu', event => event.preventDefault());

            // 4. Disable Print Screen & Copy Shortcuts
            document.addEventListener('keyup', (e) => {
                if (e.key === 'PrintScreen') {
                    navigator.clipboard.writeText('');
                    alert('Screenshots are disabled!');
                }
            });
            
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C')) {
                    e.preventDefault();
                    // alert('Copying is disabled!');
                }
            });
        }
    </script>
</body>
</html>
