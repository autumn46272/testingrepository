<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Presenter</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <!-- START SCREEN (Presenter) -->
        <div id="start-screen" class="card" style="text-align: center;">
            <h1>Simple Quiz Presenter</h1>
            <p>Start a new quiz session to generate a unique code for students.</p>
            <button class="btn" onclick="createExam()">Start New Session</button>
            <p id="start-error" style="color:red; margin-top:10px;"></p>
        </div>

        <!-- SESSION VIEW -->
        <div id="session-screen" class="card" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h1>Presenter View</h1>
                <button onclick="endSession()" style="background:#ef4444; color:white; border:none; padding:5px 12px; border-radius:4px; cursor:pointer; font-size:0.9rem; margin-right:15px;">End Session</button>
                <div class="status-badge" style="background:var(--primary-color); color:white; font-size: 1.2rem; padding: 5px 15px;">
                    Code: <span id="exam-code-display" style="font-weight:bold;">--------</span>
                </div>
            </div>
            
            <div id="loading" class="loading">Loading questions...</div>
            
            <div id="quiz-content" style="display: none;">
                <h2 id="q-counter">Question 1</h2>
                <div class="status-badge" style="display: inline-block; margin-bottom: 20px;">Live Control</div>
                
                <p id="q-text" class="question-text"></p>
                
                <div class="options-grid" id="options-container">
                    <!-- Options will be injected here -->
                </div>

                <div class="controls">
                    <button class="btn" id="prev-btn" onclick="changeQuestion(-1)">Previous</button>
                    <button class="btn" id="next-btn" onclick="changeQuestion(1)">Next Question</button>
                </div>
                
                <div style="margin-top: 20px;">
                    <a href="#" id="results-link" target="_blank" style="color: var(--primary-color); text-decoration: none;">View Results &xrarr;</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let examCode = '';
        let examId = 0; // Added examId
        let currentQuestionIndex = 0;
        let questions = []; // Loaded from JSON for display locally
        let currentState = { currentQuestionIndex: 0 };

        async function createExam() {
            try {
                const res = await fetch('api.php?action=create_exam');
                const data = await res.json();
                
                if (data.status === 'success') {
                    examCode = data.exam_code;
                    examId = data.exam_id; // Capture ID
                    document.getElementById('exam-code-display').innerText = examCode;
                    document.getElementById('results-link').href = `results.php?exam_id=${examId}`; // Use ID
                    
                    document.getElementById('start-screen').style.display = 'none';
                    document.getElementById('session-screen').style.display = 'block';
                    
                    initSession(); 
                } else {
                    document.getElementById('start-error').innerText = 'Error: ' + (data.message || 'Creating session failed');
                }
            } catch (e) {
                console.error(e);
                document.getElementById('start-error').innerText = 'Connection Message: ' + e.message;
            }
        }

        async function endSession() {
            if(confirm('Are you sure you want to end this session? All students will be disconnected.')) {
                try {
                    await fetch('api.php?action=end_exam', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ id: examId }) // Send ID
                    });
                    window.location.reload();
                } catch (e) {
                    console.error("Error ending session", e);
                    alert("Failed to end session properly");
                }
            }
        }

        // Initialize Session Data
        async function initSession() {
            try {
                // Fetch state
                const response = await fetch(`api.php?action=get_state&code=${examCode}`);
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Fetch full list from JSON just for navigation/display
                    const qResponse = await fetch('data/questions.json');
                    questions = await qResponse.json();
                    
                    currentState = data.state;
                    render();
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('quiz-content').style.display = 'block';
                }
            } catch (error) {
                console.error('Error initializing:', error);
                document.getElementById('loading').innerText = 'Error loading quiz data.';
            }
        }

        async function updateState(newIndex) {
            try {
                const response = await fetch('api.php?action=update_state', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        code: examCode,
                        currentQuestionIndex: newIndex
                    })
                });
                
                const data = await response.json();
                if (data.status === 'success') {
                    currentState.currentQuestionIndex = newIndex;
                    render();
                }
            } catch (e) {
                console.error(e);
            }
        }

        function changeQuestion(delta) {
            const newIndex = currentState.currentQuestionIndex + delta;
            if (newIndex >= 0 && newIndex < questions.length) {
                updateState(newIndex);
            }
        }

        function render() {
            const index = currentState.currentQuestionIndex;
            const question = questions[index];
            
            document.getElementById('q-counter').innerText = `Question ${index + 1} of ${questions.length}`;
            document.getElementById('q-text').innerText = question.question;
            
            const optsContainer = document.getElementById('options-container');
            optsContainer.innerHTML = '';
            
            const infoDiv = document.createElement('div');
            infoDiv.style.marginTop = '20px';
            infoDiv.style.padding = '10px';
            infoDiv.style.backgroundColor = '#ecfdf5';
            infoDiv.style.color = '#047857';
            infoDiv.style.borderRadius = '8px';
            infoDiv.innerText = "Students are answering...";
            optsContainer.appendChild(infoDiv);

            document.getElementById('prev-btn').disabled = index === 0;
            document.getElementById('next-btn').disabled = index === questions.length - 1;
        }
    </script>
</body>
</html>
