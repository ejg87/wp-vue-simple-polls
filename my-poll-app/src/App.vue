<template>
  <div id="app">
    <div v-for="poll in polls" :key="poll.id">
      <h2>{{ poll.title }}</h2>
      <form @submit.prevent="submitVote(poll.id)">
        <div v-for="answer in poll.answers" :key="answer.answer">
          <!-- Display the answer text without HTML tags and the vote count -->
          <input type="radio" :value="answer.answer" :name="`poll-${poll.id}`" v-model="selectedAnswers[poll.id]"> 
          {{ stripTags(answer.answer) }} ({{ answer.votes }} votes)
        </div>
        <button type="submit">Vote</button>
      </form>
    </div>
  </div>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';

export default {
  setup() {
    const polls = ref([]);
    const selectedAnswers = reactive({});

    const fetchPolls = async () => {
      const response = await fetch('http://spacemarinefan.local/wp-json/simple-poll/v1/polls');
      const data = await response.json();
      polls.value = data;
    };

    const submitVote = async (pollId) => {
      const answer = selectedAnswers[pollId];
      if (answer) {
        await fetch(`http://spacemarinefan.local/wp-json/simple-poll/v1/submit-vote/${pollId}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ answer }),
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert(`Vote submitted successfully for ${stripTags(answer)}`);
            fetchPolls(); // Re-fetch polls to update vote counts
          }
        });
      }
    };

    // Method to strip HTML tags from a string
    const stripTags = (input) => input.replace(/<\/?[^>]+(>|$)/g, "");

    onMounted(fetchPolls);

    return {
      polls,
      selectedAnswers,
      submitVote,
      stripTags,
    };
  },
};
</script>
